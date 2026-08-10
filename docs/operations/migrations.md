# Operação de migrations

## Objetivo

Este documento define o procedimento operacional para executar e recuperar migrations do backend PHP da Agenda Inteligente.

Ele complementa, sem substituir:

- `docs/architecture/adr/0006-configuracao-e-segredos.md`;
- `docs/architecture/adr/0007-mecanismo-de-migrations.md`.

As decisões arquiteturais permanecem nos ADRs. Este documento descreve como operá-las de forma reproduzível.

## Escopo

O procedimento cobre:

- preparação do ambiente;
- seleção da configuração;
- validação da configuração;
- backup;
- execução explícita das migrations;
- validação posterior;
- reexecução sem migrations pendentes;
- falhas de configuração;
- falhas de concorrência;
- divergências de histórico;
- falhas parciais de DDL;
- recuperação;
- rollback de implantação;
- primeira aplicação de um banco novo.

## Componentes operacionais

O mecanismo atual utiliza:

```text
backend/bin/validate-config.php
backend/bin/migrate.php
database/migrations/
```

O comando de migrations reutiliza:

```text
ConfigLoader
    ↓
Connection
    ↓
MigrationRunner
    ↓
MigrationLock
    ↓
schema_migrations
```

Não existe mecanismo independente de conexão ou configuração para migrations.

## Regra fundamental

Migrations são operações explícitas de implantação.

Elas nunca deverão ser executadas automaticamente durante:

- bootstrap HTTP;
- health check;
- login;
- registro;
- requisições da API;
- carregamento de páginas;
- inicialização normal da aplicação.

Não deverá existir endpoint HTTP público para executar migrations.

## Configuração efetiva

A configuração do backend segue o ADR 0006.

A ordem de resolução é:

1. caminho absoluto informado em `AGENDA_CONFIG_FILE`;
2. fallback local `backend/config/app.php`.

Em produção deverá ser utilizado um arquivo privado fora do `DocumentRoot`.

Exemplo conceitual:

```text
/home/CONTA/private_config/agenda-inteligente/app.php
```

A variável:

```text
AGENDA_CONFIG_FILE
```

contém somente o caminho do arquivo privado.

Ela não deverá conter credenciais.

## Pré-requisitos

Antes de qualquer execução de migrations, confirmar:

- release correta do código;
- PHP compatível com o projeto;
- extensão PDO MySQL disponível;
- banco MySQL ou MariaDB acessível;
- configuração privada disponível;
- configuração apontando para o banco correto;
- diretório `database/migrations/` presente;
- ausência de outra execução intencional de migrations;
- backup adequado quando houver alteração relevante de schema ou dados.

Em produção, confirmar também:

- `app.environment=production`;
- `app.debug=false`;
- HTTPS configurado;
- arquivo privado fora do diretório público;
- permissões adequadas para leitura da configuração.

## Identificação do ambiente

Antes da execução, o operador deverá saber explicitamente qual ambiente está sendo alterado.

Exemplos:

```text
development
testing
production
```

Nunca utilizar configuração de produção em testes automatizados.

Nunca assumir o banco de destino apenas pelo nome da máquina ou diretório do projeto.

## Validação da configuração

Com `AGENDA_CONFIG_FILE` definido:

```bash
AGENDA_CONFIG_FILE=/caminho/absoluto/app.php \
php backend/bin/validate-config.php
```

Resultado esperado:

```text
Configuração válida para o ambiente <ambiente>.
```

Código de saída esperado:

```text
0
```

Qualquer código diferente de zero bloqueia a execução das migrations até a configuração ser corrigida.

## Fallback local

Em desenvolvimento local, quando `AGENDA_CONFIG_FILE` não estiver definido, poderá ser utilizado:

```text
backend/config/app.php
```

Esse arquivo é privado e ignorado pelo Git.

O fallback local não altera a regra de produção: produção deverá preferir configuração fora do `DocumentRoot`.

## Backup

Antes de migrations com impacto relevante, produzir backup recuperável do banco.

A forma exata dependerá do ambiente e do provedor.

Exemplos de mecanismos possíveis:

- backup do cPanel;
- exportação pelo phpMyAdmin;
- ferramenta de backup do provedor;
- cliente MySQL/MariaDB disponível no servidor.

Credenciais não deverão ser colocadas diretamente em comandos que possam permanecer no histórico do shell.

O operador deverá registrar pelo menos:

- ambiente;
- banco;
- data e hora;
- release que será implantada;
- localização ou identificação do backup.

Uma migration destrutiva exige backup e plano de recuperação previamente avaliados.

## Inspeção das migrations distribuídas

Antes da execução, listar os arquivos disponíveis:

```bash
find database/migrations \
  -maxdepth 1 \
  -type f \
  -name '*.sql' \
  -printf '%f\n' \
  | sort
```

Os nomes deverão respeitar:

```text
NNN_descricao.sql
```

A ordem lexicográfica é a ordem oficial de execução.

## Execução

Depois da validação da configuração e do backup apropriado:

```bash
AGENDA_CONFIG_FILE=/caminho/absoluto/app.php \
php backend/bin/migrate.php
```

O comando deverá terminar com código:

```text
0
```

quando a execução for concluída com sucesso.

A saída normal informa a quantidade de migrations aplicadas:

```text
Migrations concluídas: N aplicada(s).
```

## Nenhuma migration pendente

Executar novamente o comando em um banco atualizado é permitido.

Resultado esperado:

```text
Migrations concluídas: 0 aplicada(s).
```

O mecanismo não deverá reexecutar migrations já registradas.

Essa reexecução é uma verificação do estado, não uma reaplicação do schema.

## Tabela `schema_migrations`

A tabela:

```text
schema_migrations
```

é gerenciada exclusivamente pela infraestrutura de migrations.

Ela não pertence ao domínio da aplicação.

Após uma execução bem-sucedida, o histórico poderá ser inspecionado com:

```sql
SELECT
    version,
    checksum,
    executed_at
FROM schema_migrations
ORDER BY version;
```

Cada migration concluída deverá possuir:

- `version`;
- checksum SHA-256;
- data e hora de registro.

## Proibição de manutenção manual do histórico

Não utilizar comandos manuais como:

```sql
INSERT INTO schema_migrations ...
UPDATE schema_migrations ...
DELETE FROM schema_migrations ...
```

para mascarar ou contornar uma falha.

Também não alterar manualmente checksums para fazer o runner aceitar um arquivo divergente.

Qualquer inconsistência de histórico exige investigação.

## Imutabilidade

Depois que uma migration tiver sido aplicada em um ambiente relevante:

- seu nome não será alterado;
- seu número não será reutilizado;
- seu conteúdo não será modificado;
- seu arquivo não será removido.

Mudanças posteriores serão feitas por nova migration.

Exemplo:

```text
001_initial_schema.sql
002_add_example_field.sql
003_fix_example_constraint.sql
```

## Checksum divergente

Se o arquivo atual possuir conteúdo diferente daquele aplicado anteriormente, o runner deverá abortar.

Nessa situação:

1. não executar alterações manuais para atualizar o checksum;
2. identificar qual versão do arquivo foi originalmente aplicada;
3. verificar histórico do Git e release implantada;
4. determinar se houve alteração indevida do arquivo;
5. restaurar o arquivo correto ou definir uma migration corretiva;
6. somente depois repetir o processo operacional.

Checksum divergente é inconsistência de histórico, não migration pendente.

## Migration aplicada com arquivo ausente

Se `schema_migrations` registrar uma versão cujo arquivo não está mais presente no pacote distribuído, o runner deverá abortar.

O procedimento é:

1. confirmar a versão registrada;
2. localizar o arquivo correspondente no histórico do repositório;
3. restaurar o arquivo sem alterar seu conteúdo;
4. revisar o pacote;
5. executar novamente o comando.

Migrations aplicadas não devem ser apagadas do repositório.

## Concorrência

O runner utiliza lock de banco para impedir duas execuções simultâneas sobre o mesmo banco.

Se outra execução possuir o lock, uma segunda execução deverá falhar antes de iniciar o fluxo de migrations.

Nesse caso:

1. confirmar se existe outra implantação ou processo executando migrations;
2. aguardar a conclusão da execução legítima;
3. confirmar que o processo anterior terminou;
4. repetir o comando.

Não forçar liberação de lock sem investigar sua origem.

A conexão de banco também funciona como proteção final: o lock pertence à sessão que o adquiriu.

## Falha de migration

A primeira migration que falhar interrompe imediatamente a sequência.

Migrations posteriores não serão executadas.

A migration com erro não será registrada como concluída.

O operador deverá registrar:

- ambiente;
- release;
- versão da migration;
- mensagem apresentada;
- horário da falha;
- estado observado do banco.

## DDL e falha parcial

MySQL e MariaDB podem realizar commits implícitos em comandos DDL.

Portanto, uma migration pode:

1. executar uma alteração de schema;
2. falhar em uma instrução posterior;
3. permanecer sem registro em `schema_migrations`;
4. deixar parte da alteração materializada no banco.

Esse estado não será reparado automaticamente.

## Procedimento para falha parcial

Ao suspeitar de execução parcial:

1. não executar novamente a migration imediatamente;
2. não registrar manualmente a migration;
3. não alterar seu checksum;
4. interromper a implantação;
5. preservar a mensagem de erro;
6. inspecionar quais objetos foram efetivamente criados ou alterados;
7. comparar o estado encontrado com o SQL da migration;
8. consultar o backup anterior;
9. definir uma recuperação explícita;
10. somente retomar as migrations depois de o banco estar em estado conhecido.

A restauração a partir de backup somente deverá ser escolhida quando for operacionalmente segura e não implicar perda de gravações legítimas realizadas depois do backup.

Em ambiente isolado ou durante uma janela controlada sem novas escritas, restaurar o banco ao estado anterior à migration poderá ser a estratégia mais simples.

Em ambiente ativo, antes de restaurar um backup completo, deverá ser avaliado se existem dados válidos posteriores que seriam perdidos. Quando houver esse risco, a recuperação deverá ser explicitamente planejada, podendo envolver correção controlada do estado parcial ou migration corretiva, conforme o caso.

Depois da recuperação, o banco deverá estar em estado conhecido e compatível com o histórico de migrations antes de o runner ser executado novamente.

## Alteração de migration ainda não aplicada

Uma migration que nunca foi aplicada em nenhum ambiente relevante ainda poderá ser corrigida durante o desenvolvimento.

Isso deixa de ser permitido assim que ela tiver sido aplicada em um ambiente relevante.

Se ocorreu falha parcial, alterar o arquivo não deve ser utilizado como substituto para analisar o estado materializado no banco.

Primeiro o estado do banco precisa ser entendido e recuperado.

## Migration corretiva

Quando uma alteração já aplicada precisar ser modificada ou revertida, utilizar nova migration.

Exemplo:

```text
004_add_example.sql
005_revert_example.sql
```

Não reescrever `004_add_example.sql`.

## Down migrations

O mecanismo atual não possui execução automática de migrations `down`.

Isso é intencional.

Rollback de banco exige decisão explícita e não é inferido automaticamente a partir de rollback de código.

## Rollback de implantação

Rollback de aplicação e rollback de banco são operações distintas.

Retornar o PHP ou frontend para uma versão anterior não garante que o schema anterior tenha sido restaurado.

Antes de uma migration que possa quebrar compatibilidade, avaliar:

- se a versão anterior da aplicação continua compatível;
- se a alteração é aditiva;
- se existe janela de compatibilidade;
- se será necessária migration corretiva;
- se o backup permite restauração;
- qual indisponibilidade seria necessária.

## Alterações destrutivas

Exemplos:

```text
DROP TABLE
DROP COLUMN
ALTER COLUMN com perda de dados
DELETE em massa
```

Essas alterações exigem análise explícita de:

- impacto;
- backup;
- compatibilidade;
- perda de dados;
- estratégia de recuperação;
- possibilidade de implantação em etapas.

Não tratar alterações destrutivas como manutenção rotineira.

## Verificação posterior

Depois de uma execução bem-sucedida:

1. confirmar código de saída `0`;
2. conferir quantidade de migrations aplicadas;
3. verificar `schema_migrations`;
4. confirmar que todas as migrations esperadas foram registradas;
5. executar verificações estruturais específicas da release;
6. somente então prosseguir com testes funcionais da aplicação.

Uma segunda execução poderá ser utilizada para confirmar que não há novas migrations pendentes:

```text
Migrations concluídas: 0 aplicada(s).
```

## Logs e informações sensíveis

Mensagens operacionais podem conter:

- versão da migration;
- classe da exceção em log técnico;
- descrição do erro;
- quantidade aplicada.

Nunca registrar:

- senha do banco;
- conteúdo completo da configuração privada;
- chave de API;
- outros segredos.

## Hospedagem compartilhada

O mecanismo atualmente suportado é CLI:

```bash
php backend/bin/migrate.php
```

quando terminal ou SSH estiver disponível.

Se a hospedagem não oferecer esse recurso, uma alternativa operacional futura deverá:

- reutilizar o mesmo `MigrationRunner`;
- reutilizar a mesma configuração validada;
- manter o mesmo locking;
- manter os mesmos checksums;
- manter os mesmos códigos de sucesso/falha conceituais;
- ser explicitamente controlada.

Não deverá ser criado endpoint HTTP público para migrations.

A alternativa específica para provedores sem terminal será definida somente quando houver necessidade concreta e características conhecidas da hospedagem.

## Primeira migration do projeto

A migration inicial atual é:

```text
001_initial_schema.sql
```

Enquanto ainda não tiver sido aplicada em ambiente relevante, sua revisão deverá ocorrer antes da primeira execução.

Depois da primeira aplicação relevante ela se torna imutável.

## Primeira execução em banco novo

A primeira execução real deverá possuir um checkpoint próprio.

Antes dela:

1. confirmar configuração do ambiente;
2. confirmar banco de destino;
3. inspecionar o estado atual do banco;
4. revisar integralmente `001_initial_schema.sql`;
5. confirmar seu checksum;
6. validar compatibilidade com a versão real de MySQL/MariaDB;
7. produzir backup quando aplicável;
8. confirmar que não existe execução concorrente;
9. validar a configuração;
10. somente então executar `backend/bin/migrate.php`.

Depois da execução:

1. confirmar código `0`;
2. confirmar tabelas criadas;
3. confirmar estrutura esperada;
4. confirmar registro de `001_initial_schema` em `schema_migrations`;
5. confirmar checksum registrado;
6. executar novamente o runner e esperar `0 aplicada(s)`;
7. realizar testes funcionais.

## Estado atual

Na criação deste runbook, a infraestrutura de migrations já possui:

- descoberta determinística;
- validação de nomes;
- checksum SHA-256;
- snapshot consistente do conteúdo;
- planner de pendências;
- tabela `schema_migrations`;
- runner;
- proteção de concorrência por lock;
- tratamento de falha dupla;
- comando CLI;
- testes de integração com banco dedicado.

A primeira execução real de:

```text
001_initial_schema.sql
```

permanece uma etapa separada e controlada.

# ADR 0007 — Mecanismo de migrations

## Status

Aceito.

## Data

2026-08-07.

## Contexto

A Agenda Inteligente utilizará PHP 8.2 com MySQL ou MariaDB em hospedagem compartilhada.

O banco precisa evoluir de forma:

- versionada;
- previsível;
- auditável;
- compatível com implantação manual ou automatizada;
- independente de frameworks;
- segura para ambientes já existentes.

O repositório possui migrations SQL em:

```text
database/migrations/
```

A primeira migration é:

```text
001_initial_schema.sql
```

Essa migration ainda não foi aplicada em nenhum ambiente do novo backend PHP e foi alinhada aos ADRs antes da primeira execução.

O planejamento arquitetural já determina que migrations deverão:

- ser versionadas;
- possuir ordem determinística;
- executar apenas uma vez;
- permitir auditoria da versão do schema;
- evitar alterações destrutivas sem tratamento explícito.

O fluxo de implantação definido no ADR 0006 também exige a execução de migrations antes dos testes funcionais do sistema.

Faltava definir o mecanismo responsável por descobrir, executar e registrar essas migrations.

## Problema

É necessário definir:

- como migrations serão identificadas;
- como sua ordem será determinada;
- como migrations já aplicadas serão registradas;
- quem será responsável pela tabela de controle;
- quando uma migration será considerada concluída;
- como alterações indevidas em migrations aplicadas serão detectadas;
- como falhas serão tratadas;
- como evitar execução acidental durante requisições HTTP;
- qual será a estratégia inicial de rollback.

A solução precisa funcionar em hospedagem compartilhada e não poderá depender de:

- processos residentes;
- Docker;
- ferramentas administrativas externas obrigatórias;
- frameworks apenas para migrations;
- acesso SSH permanente.

## Decisão

Será utilizado um executor próprio e enxuto denominado conceitualmente `MigrationRunner`.

O runner utilizará PDO e a mesma configuração de banco validada pela aplicação.

As migrations de domínio permanecerão em arquivos SQL versionados.

## Diretório

As migrations serão armazenadas em:

```text
database/migrations/
```

Esse diretório não deverá estar acessível publicamente pelo servidor HTTP.

## Convenção de nomes

Os arquivos seguirão o formato:

```text
NNN_descricao.sql
```

Exemplos:

```text
001_initial_schema.sql
002_add_example_field.sql
003_create_example_index.sql
```

A versão da migration será o nome completo do arquivo sem a extensão `.sql`.

Exemplo:

```text
001_initial_schema
```

Os nomes deverão ser:

- únicos;
- estáveis;
- ordenáveis lexicograficamente;
- descritivos;
- preservados após aplicação.

A numeração sequencial começará em `001`.

O prefixo `000` é inválido e não poderá ser utilizado por uma migration.

Cada prefixo sequencial `NNN` será exclusivo dentro do histórico de migrations.

Duas migrations não poderão compartilhar o mesmo prefixo numérico, ainda que possuam descrições diferentes.

Exemplo inválido:

```text
020_first_change.sql
020_second_change.sql
```

Uma vez utilizado, um número de migration não poderá ser reutilizado, independentemente de a migration já ter sido aplicada ou de seu arquivo permanecer apenas no histórico do projeto.

## Ordem de execução

O `MigrationRunner` descobrirá os arquivos válidos no diretório de migrations e os ordenará lexicograficamente pelo nome.

Essa ordenação será a ordem oficial de execução.

O runner não dependerá da ordem retornada pelo sistema de arquivos.

## Tabela de controle

O controle das migrations aplicadas será responsabilidade exclusiva do `MigrationRunner`.

As migrations de domínio não deverão criar nem atualizar essa tabela.

O runner deverá garantir a existência de:

```sql
CREATE TABLE schema_migrations (
    version VARCHAR(190) NOT NULL,
    checksum CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (version)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

A coluna `version` identifica a migration.

A coluna `checksum` armazenará o SHA-256 hexadecimal do conteúdo exato do arquivo aplicado.

A coluna `executed_at` registra quando a migration foi concluída e registrada.

## Responsabilidade da tabela `schema_migrations`

A tabela `schema_migrations` pertence à infraestrutura de migrations, e não ao domínio da aplicação.

Por isso:

- não fará parte de `001_initial_schema.sql`;
- será criada ou validada pelo runner;
- não será manipulada por controllers ou repositories de domínio;
- não será exposta por endpoints HTTP.

## Descoberta de migrations pendentes

Antes de executar migrations, o runner deverá:

1. garantir a existência de `schema_migrations`;
2. descobrir os arquivos de migration;
3. validar seus nomes;
4. ordenar os arquivos;
5. calcular seus checksums;
6. consultar as versões já registradas;
7. validar migrations anteriormente aplicadas;
8. identificar as migrations ainda pendentes.

Uma migration cuja versão já conste em `schema_migrations` não será executada novamente.

## Imutabilidade

Uma migration aplicada será considerada imutável.

Alterações posteriores no schema deverão ser feitas por uma nova migration.

Exemplo:

```text
001_initial_schema.sql
002_add_user_field.sql
```

Não será permitido modificar `001_initial_schema.sql` depois que ela tiver sido aplicada em um ambiente relevante.

## Verificação por checksum

Para cada migration já registrada, o runner comparará:

```text
checksum registrado
        versus
SHA-256 atual do arquivo
```

Se os valores forem diferentes, a execução deverá ser interrompida.

O runner não deverá:

- atualizar automaticamente o checksum;
- executar novamente a migration;
- ignorar a divergência.

A divergência será tratada como inconsistência de histórico e exigirá investigação explícita.

## Arquivos ausentes

As migrations aplicadas deverão permanecer versionadas no repositório.

Se `schema_migrations` registrar uma versão cujo arquivo correspondente não exista no conjunto de migrations distribuído, o runner deverá tratar a situação como inconsistente e abortar.

Migrations antigas não deverão ser apagadas apenas porque já foram executadas.

## Execução

Para cada migration pendente, o fluxo conceitual será:

```text
ler arquivo
    ↓
calcular checksum
    ↓
executar SQL
    ↓
confirmar sucesso da execução
    ↓
registrar version + checksum
```

O registro em `schema_migrations` somente ocorrerá depois que a execução da migration terminar sem erro.

## Falhas

A primeira falha interromperá imediatamente a sequência.

Migrations posteriores não serão executadas enquanto houver uma migration anterior pendente ou inconsistente.

O erro deverá:

- produzir código de saída diferente de zero;
- identificar a versão que falhou;
- preservar a exceção técnica para logs ou console apropriado;
- não registrar a migration como concluída.

## Atomicidade e DDL

O mecanismo não presumirá que uma migration SQL inteira possa ser revertida por transação.

MySQL e MariaDB podem realizar commits implícitos em operações DDL.

Portanto, uma migration que falhar após executar parcialmente alterações de schema poderá deixar alterações já materializadas no banco.

Nessa situação:

- a migration não será registrada como concluída;
- migrations posteriores não serão executadas;
- o estado deverá ser analisado antes de nova tentativa;
- não haverá tentativa automática de esconder ou reparar o estado parcial.

As migrations deverão ser pequenas e focadas para reduzir esse risco.

## Idempotência

Migrations são operações versionadas de execução única.

Não será requisito tornar toda migration genericamente idempotente.

Construções como:

```text
IF NOT EXISTS
IF EXISTS
```

somente deverão ser usadas quando fizerem parte da semântica intencional da migration.

Elas não deverão ser utilizadas apenas para mascarar:

- migration parcialmente aplicada;
- histórico inconsistente;
- alteração manual não documentada no banco.

## Execução fora do ciclo HTTP

Migrations nunca serão executadas automaticamente durante:

- bootstrap de uma requisição;
- acesso ao health check;
- login;
- registro;
- qualquer endpoint da API.

A execução será explícita e operacional.

O mecanismo inicial será disponibilizado por script CLI do projeto.

O mesmo componente de infraestrutura poderá futuramente ser chamado por outro mecanismo de implantação controlado, sem incorporar migrations ao fluxo normal da API.

## Hospedagem compartilhada

A solução não dependerá de daemon ou processo persistente.

Quando SSH ou terminal estiver disponível, o script CLI poderá ser executado diretamente.

Caso o provedor exija outro mecanismo operacional, ele deverá reutilizar o mesmo `MigrationRunner` e manter as mesmas garantias.

Não será criado endpoint HTTP público para aplicar migrations.

## Integração com PDO

O `MigrationRunner` receberá uma instância de `PDO`.

Ele não será responsável por:

- descobrir senha de banco;
- carregar arquivos privados de configuração;
- criar uma segunda política de conexão.

A criação da conexão continuará centralizada na infraestrutura existente.

Essa separação preserva SRP e permite testar o runner sem acoplá-lo ao bootstrap HTTP.

## Down migrations

A primeira implementação não utilizará migrations automáticas de `down`.

Uma migration aplicada representa avanço do schema.

Quando uma alteração precisar ser revertida, deverá ser criada uma migration corretiva explícita quando isso for seguro.

Exemplo:

```text
004_add_example.sql
005_revert_example.sql
```

Isso preserva histórico e evita reescrever migrations já utilizadas.

## Rollback de implantação

Rollback de código e rollback de banco são problemas relacionados, mas distintos.

A existência de um rollback do pacote PHP ou frontend não implica automaticamente reversão do schema.

Alterações incompatíveis de banco deverão considerar estratégia de implantação compatível, como:

- mudanças aditivas;
- período de compatibilidade;
- migration corretiva;
- backup quando a alteração envolver risco relevante.

O procedimento operacional completo de rollback será tratado na documentação de implantação.

## Alterações destrutivas

Migrations destrutivas exigirão atenção explícita.

Operações como:

```text
DROP TABLE
DROP COLUMN
ALTER COLUMN com perda de dados
DELETE em massa
```

não deverão ser introduzidas como manutenção rotineira sem avaliar:

- backup;
- compatibilidade com a versão anterior da aplicação;
- possibilidade de perda de dados;
- estratégia de recuperação.

## Logs

O runner poderá informar:

- migration descoberta;
- migration ignorada por já estar aplicada;
- migration iniciada;
- migration concluída;
- migration que falhou;
- quantidade de migrations aplicadas.

Não deverá registrar:

- senha do banco;
- conteúdo de configuração privada;
- dados sensíveis de aplicação.

## Concorrência

A execução operacional deverá evitar dois runners aplicando migrations simultaneamente.

A primeira implementação deverá possuir proteção contra concorrência antes de executar migrations pendentes.

O mecanismo concreto poderá utilizar recurso de locking do banco compatível com MySQL e MariaDB, desde que:

- possua timeout;
- libere o lock ao finalizar;
- aborte quando o lock não puder ser adquirido;
- não dependa de estado em memória do processo.

A implementação e seus testes deverão validar a estratégia escolhida.

## Testes

O mecanismo deverá possuir testes para, no mínimo:

- ordenação determinística;
- identificação da versão pelo nome do arquivo;
- descoberta de migrations pendentes;
- não reexecução de migrations aplicadas;
- registro somente após sucesso;
- interrupção na primeira falha;
- checksum consistente;
- detecção de checksum divergente;
- detecção de migration aplicada cujo arquivo desapareceu;
- rejeição de nomes inválidos;
- comportamento da proteção contra concorrência.

Testes que dependam especificamente de comportamento DDL de MySQL ou MariaDB deverão ser separados dos testes puramente determinísticos do runner.

## Consequências

### Positivas

- não adiciona framework apenas para migrations;
- funciona com o backend PHP enxuto;
- preserva histórico explícito do schema;
- detecta alteração indevida de migration aplicada;
- separa migrations do ciclo HTTP;
- mantém o mecanismo compatível com hospedagem compartilhada;
- facilita auditoria de implantação;
- preserva SRP entre conexão, runner e migrations.

### Negativas

- passa a existir código próprio de infraestrutura para manter;
- rollback de DDL não será automático;
- falhas parciais de DDL podem exigir intervenção;
- testes reais de compatibilidade ainda dependerão de MySQL ou MariaDB;
- proteção contra concorrência adiciona responsabilidade ao runner.

## Alternativas consideradas

### Executar manualmente cada arquivo SQL

Rejeitado como mecanismo principal porque:

- não controla automaticamente o que já foi aplicado;
- aumenta risco de execução duplicada;
- não verifica integridade histórica;
- dificulta implantação repetível.

### Criar `schema_migrations` dentro da primeira migration

Rejeitado.

A tabela de controle pertence ao executor.

Além disso, a própria migration não deverá registrar a si mesma como concluída antes de o mecanismo externo confirmar seu sucesso.

### Executar migrations no bootstrap HTTP

Rejeitado.

Isso colocaria alteração de schema no caminho de requisições normais e poderia causar:

- concorrência;
- aumento de latência;
- falhas de disponibilidade;
- mudanças de banco disparadas por usuários.

### Adotar framework somente para migrations

Não será feito inicialmente.

A necessidade atual pode ser atendida por um componente pequeno baseado em PDO.

Essa decisão poderá ser revista caso o projeto passe a utilizar um framework cuja infraestrutura de migrations ofereça benefício suficiente para justificar a mudança.

### Down migrations automáticas

Não serão adotadas inicialmente.

A prioridade será manter histórico progressivo e explícito do schema.

## Relação com outros ADRs

Este ADR complementa:

- ADR 0001 — Arquitetura do backend PHP;
- ADR 0004 — Modelo de eventos e contatos;
- ADR 0005 — Cache meteorológico;
- ADR 0006 — Estratégia de configuração e segredos.

O ADR 0006 define que migrations fazem parte do fluxo de implantação.

Este ADR define o mecanismo responsável por executá-las e registrar seu estado.

## Critérios de aceite

A decisão será considerada implementada quando:

1. existir um `MigrationRunner` separado do ciclo HTTP;
2. a tabela `schema_migrations` for gerenciada pelo runner;
3. migrations forem descobertas e ordenadas deterministicamente;
4. migrations aplicadas não forem reexecutadas;
5. checksums forem registrados e validados;
6. divergências de histórico interromperem a execução;
7. uma migration somente for registrada depois do sucesso;
8. a primeira falha impedir migrations posteriores;
9. existir proteção contra execução concorrente;
10. existir um comando operacional explícito para aplicar migrations;
11. houver testes automatizados para as regras determinísticas;
12. a documentação de implantação indicar como executar o mecanismo.

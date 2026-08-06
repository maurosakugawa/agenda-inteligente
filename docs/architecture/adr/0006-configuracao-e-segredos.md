# ADR 0006 — Estratégia de configuração e segredos

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente será implantada em hospedagem compartilhada com Apache, PHP 8.2 e MySQL ou MariaDB. O frontend React/Vite será compilado estaticamente e o backend PHP usará a mesma origem.

A aplicação precisa configurar:

- ambiente, debug, timezone e URL-base;
- conexão com o banco;
- parâmetros de sessão;
- chave e URL da OpenWeather;
- tempos de expiração e cache;
- caminhos operacionais de logs e armazenamento.

Alguns valores são segredos:

- senha do banco;
- chave da OpenWeather;
- futuras senhas SMTP;
- futuros tokens privados.

Outros valores não são secretos, mas variam por ambiente:

- nome do ambiente;
- host, porta e nome do banco;
- timezone;
- nome e política do cookie;
- URL-base;
- TTLs;
- URLs públicas de serviços.

O repositório já possui:

```text
backend/config/app.example.php
backend/bootstrap.php
backend/src/Infrastructure/Database/Connection.php
.env.example
.gitignore
src/config/api.ts
```

O bootstrap atual procura `backend/config/app.php`, carrega o array retornado, define timezone e debug e cria a conexão PDO.

O `.gitignore` exclui:

```text
.env
.env.*
backend/config/app.php
```

e mantém versionados os arquivos de exemplo.

O frontend usa `VITE_API_BASE_URL`. Toda variável com prefixo `VITE_` é incorporada ao bundle e deve ser considerada pública.

## Problema

Definir uma estratégia única para:

- separar configuração pública e privada;
- manter segredos fora do Git e do bundle;
- manter segredos fora do diretório público;
- carregar e validar a configuração centralmente;
- permitir desenvolvimento, testes, cron e produção;
- impedir `debug` inseguro em produção;
- evitar exposição em respostas e logs;
- preservar a configuração entre implantações;
- permitir rotação e resposta a vazamentos.

A solução deve funcionar sem presumir Docker, Vault, serviço residente ou suporte uniforme a variáveis de ambiente no cPanel.

## Objetivos

A solução deverá:

- possuir uma única fonte efetiva por execução;
- manter o carregamento centralizado;
- manter segredos fora do repositório;
- preferir arquivo privado fora do `DocumentRoot`;
- falhar cedo quando a configuração for inválida;
- exibir erro genérico ao usuário;
- registrar diagnóstico sem valores sensíveis;
- permitir configuração local simples;
- permitir configuração específica de testes;
- permitir uso idêntico por requisições web e cron;
- não exigir biblioteca Dotenv;
- permitir migração futura para cofre de segredos.

## Não objetivos

Esta decisão não pretende:

- criar cofre criptográfico próprio;
- criptografar segredos com chave armazenada ao lado;
- definir credenciais reais;
- editar segredos por tela administrativa;
- armazenar segredos no banco;
- criar configuração por usuário;
- permitir acesso do frontend a segredos;
- implementar rotação automática.

## Classificação

## Configuração pública do frontend

Valores `VITE_*` são públicos.

Exemplo:

```text
VITE_API_BASE_URL
```

Podem aparecer no navegador e no bundle. Não podem conter senhas, tokens ou chaves privadas.

## Configuração privada do backend

Exemplos:

```text
database.password
weather.api_key
```

Esses valores nunca serão enviados ao frontend, retornados pelo health check ou incluídos no Git.

## Configuração operacional do backend

Exemplos:

```text
app.environment
app.debug
app.timezone
app.base_url
database.host
database.port
database.database
database.username
database.charset
session.name
session.secure
session.same_site
session.idle_timeout
session.absolute_timeout
weather.base_url
weather.cache_seconds
```

Esses valores continuarão no arquivo privado efetivo porque variam por ambiente e precisam ser validados em conjunto.

## Alternativas consideradas

## Segredos no código versionado

### Avaliação

Rejeitada. Expõe credenciais no histórico, clones, diffs, branches e pull requests.

## Backend baseado somente em `.env`

### Vantagens

- formato conhecido;
- uso local simples.

### Desvantagens

- adiciona parser ou dependência;
- exige conversão manual de tipos;
- cria duas fontes se `app.php` continuar existindo;
- confunde `.env` privado do backend com `.env` público do Vite;
- proteção varia na hospedagem compartilhada.

### Avaliação

Não escolhida para o backend inicial.

O `.env` continuará permitido somente para configuração pública do frontend.

## Somente variáveis de ambiente

### Vantagens

- padrão adequado a contêineres e nuvem;
- segredos não precisam ficar na árvore da aplicação.

### Desvantagens

- suporte variável no cPanel;
- Apache, PHP-FPM e cron podem receber ambientes diferentes;
- muitos valores aumentam a inconsistência;
- tipos precisam ser convertidos.

### Avaliação

Não escolhida como única fonte. Uma variável poderá selecionar o caminho do arquivo privado.

## Arquivo PHP privado dentro do projeto

### Avaliação

Aceito como fallback local:

```text
backend/config/app.php
```

Ele continuará ignorado pelo Git.

## Arquivo PHP privado fora do `DocumentRoot`

### Avaliação

Escolhido para produção.

Exemplo conceitual:

```text
/home/CONTA/private_config/agenda-inteligente/app.php
```

O caminho exato dependerá da hospedagem.

## Decisão

Adotar configuração em array PHP com:

1. arquivo de exemplo versionado;
2. arquivo real não versionado;
3. arquivo real de produção fora do `DocumentRoot`;
4. variável opcional para selecionar o caminho;
5. fallback local previsível;
6. validação centralizada;
7. frontend restrito a variáveis públicas `VITE_*`;
8. ausência de carregamento automático de `.env` no backend.

## Resolução do arquivo efetivo

A ordem será:

1. `AGENDA_CONFIG_FILE`;
2. `backend/config/app.php`.

Exemplo conceitual:

```php
$configPath = getenv('AGENDA_CONFIG_FILE');

if (!is_string($configPath) || trim($configPath) === '') {
    $configPath = AGENDA_ROOT . '/config/app.php';
}
```

`AGENDA_CONFIG_FILE` não é segredo. Ela contém somente o caminho absoluto do arquivo privado.

Não haverá merge automático entre:

- `.env`;
- variáveis `DB_*`;
- `app.php`;
- constantes espalhadas;
- valores definidos em controllers.

Cada execução terá exatamente uma fonte efetiva.

## Arquivos

## Exemplo versionado

Continuará existindo:

```text
backend/config/app.example.php
```

Ele deverá:

- documentar a estrutura;
- usar valores fictícios;
- mostrar tipos e opções;
- manter senha claramente falsa;
- manter chave meteorológica vazia;
- nunca conter credenciais reais.

## Configuração local

No desenvolvimento:

```text
backend/config/app.php
```

Esse arquivo:

- será criado pelo desenvolvedor;
- continuará ignorado;
- conterá somente credenciais locais;
- será usado quando `AGENDA_CONFIG_FILE` estiver ausente.

## Configuração de produção

Em produção:

```text
/home/CONTA/private_config/agenda-inteligente/app.php
```

O arquivo:

- ficará fora do `DocumentRoot`;
- não fará parte do pacote;
- não será sobrescrito em deploys;
- terá permissões restritivas;
- será indicado por `AGENDA_CONFIG_FILE`.

## Configuração do frontend

O frontend continuará usando `.env.example` e arquivos locais ignorados.

Valor padrão:

```text
VITE_API_BASE_URL=
```

A mesma origem continuará sendo a regra.

Nunca serão aceitos no frontend:

```text
DB_PASSWORD
OPENWEATHER_API_KEY
SMTP_PASSWORD
PRIVATE_TOKEN
SESSION_SECRET
```

Prefixar um segredo com `VITE_` será tratado como vazamento.

## Estrutura inicial

Exemplo conceitual sem credenciais reais:

```php
return [
    'app' => [
        'environment' => 'production',
        'debug' => false,
        'timezone' => 'America/Sao_Paulo',
        'base_url' => 'https://exemplo.com',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'nome_do_banco',
        'username' => 'usuario_do_banco',
        'password' => 'segredo',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => 'AGENDA_INTELIGENTE_SESSID',
        'secure' => true,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ],

    'weather' => [
        'api_key' => 'segredo',
        'base_url' => 'https://api.openweathermap.org',
        'cache_seconds' => 1800,
    ],
];
```

Esse trecho é somente estrutural. Valores reais nunca serão inseridos na documentação.

## Carregador centralizado

Será criada uma responsabilidade como:

```text
backend/src/Infrastructure/Config/ConfigLoader.php
```

Ela deverá:

- resolver o caminho;
- confirmar existência e leitura;
- carregar o arquivo;
- confirmar que o retorno é array;
- validar estrutura e tipos;
- aplicar apenas defaults seguros;
- rejeitar produção insegura;
- devolver a configuração ao bootstrap.

Nenhuma outra camada deverá carregar arquivos ou chamar `getenv()` diretamente.

Controllers, services e repositories receberão apenas os subconjuntos necessários.

## Validação estrutural

Serão obrigatórias as chaves:

```text
app.environment
app.debug
app.timezone
app.base_url

database.host
database.port
database.database
database.username
database.password
database.charset

session.name
session.secure
session.same_site
session.idle_timeout
session.absolute_timeout

weather.api_key
weather.base_url
weather.cache_seconds
```

Booleanos deverão ser booleanos reais. Inteiros deverão ser inteiros válidos.

Strings como `"false"` ou `"off"` não serão convertidas silenciosamente em decisões críticas.

## Validação semântica

Deverá validar:

- ambiente permitido;
- timezone reconhecido;
- URL-base válida;
- porta entre 1 e 65535;
- banco, usuário e senha não vazios;
- charset permitido;
- nome de sessão válido;
- `SameSite` permitido;
- timeouts positivos;
- timeout absoluto maior ou igual ao ocioso;
- OpenWeather em HTTPS;
- TTL meteorológico positivo;
- chave meteorológica presente quando clima estiver habilitado.

## Ambientes permitidos

```text
development
testing
production
```

Outros valores serão rejeitados.

## Regras de produção

Quando:

```text
app.environment = production
```

será obrigatório:

```text
app.debug = false
session.secure = true
app.base_url com HTTPS
```

Também serão obrigatórios:

- senha de banco não vazia;
- chave meteorológica não vazia quando usada;
- arquivo privado legível;
- configuração válida antes de iniciar sessão ou conectar ao banco.

Configuração insegura interromperá o bootstrap.

## Defaults permitidos

Defaults somente para valores não sensíveis:

```text
app.timezone = America/Sao_Paulo
database.port = 3306
database.charset = utf8mb4
session.name = AGENDA_INTELIGENTE_SESSID
session.same_site = Lax
session.idle_timeout = 1800
session.absolute_timeout = 28800
weather.base_url = https://api.openweathermap.org
weather.cache_seconds = 1800
```

Não haverá default para:

```text
database.database
database.username
database.password
weather.api_key
```

## Falha de configuração

Quando a configuração falhar:

- o bootstrap terminará;
- nenhuma rota de negócio executará;
- a resposta será genérica;
- o status será `500`;
- o diagnóstico será registrado no log privado;
- nenhum valor sensível será exibido.

Exemplo:

```json
{
  "success": false,
  "error": {
    "code": "application_configuration_error",
    "message": "A aplicação não pôde ser inicializada."
  }
}
```

Em desenvolvimento, detalhes poderão ser exibidos somente com ambiente `development` e `debug=true`, sempre com redação de segredos.

## Debug e erros

Em produção:

```text
display_errors = 0
log_errors = 1
```

O usuário não verá:

- stack trace;
- caminho absoluto;
- DSN;
- SQL;
- host ou usuário do banco;
- senha;
- chave externa;
- configuração completa.

## Logs

Logs ficarão fora do diretório público ou em diretório bloqueado.

Nunca será registrado:

- `print_r($config)`;
- `var_export($config)`;
- senha;
- chave OpenWeather;
- cookie;
- ID integral de sessão;
- token CSRF;
- `Authorization`;
- corpo de login;
- URL externa contendo `appid`.

Parâmetros sensíveis serão redigidos:

```text
appid=[REDACTED]
```

## Permissões

Quando suportado:

```text
diretório privado: 0700
arquivo privado:   0600
```

Não será utilizado `0777`.

Se o provedor exigir grupo, será usada a permissão mínima necessária e isso será documentado.

## Proteção em profundidade

A proteção principal é manter o arquivo fora do `DocumentRoot`.

Além disso:

- o servidor deverá publicar apenas a pasta pública;
- diretórios superiores não serão servidos;
- listagem de diretório ficará desativada;
- acesso direto a configuração será negado;
- `.htaccess` poderá complementar, mas não será a única barreira.

A segurança não dependerá somente de:

- nome obscuro;
- extensão `.php`;
- `.gitignore`;
- ausência de links.

## Git

O `.gitignore` continuará excluindo arquivos privados.

Porém, ele não protege arquivos já rastreados nem impede `git add -f`.

Antes de commits de configuração:

```bash
git status --short
git diff --cached --name-status
git diff --cached
```

Busca preventiva:

```bash
git grep -nE   'OPENWEATHER_API_KEY|DB_PASSWORD|SMTP_PASSWORD|PRIVATE_TOKEN'
```

Valores de exemplo deverão ser inequivocamente falsos.

## Auditoria do bundle

Depois do build:

```bash
grep -RniE   'OPENWEATHER_API_KEY|DB_PASSWORD|SMTP_PASSWORD|PRIVATE_TOKEN'   dist
```

Também deverá ser procurado localmente o valor real conhecido, sem incluí-lo em scripts versionados.

O bundle poderá conter URLs públicas e nomes de endpoints, mas nunca credenciais.

## Implantação

Fluxo inicial:

1. criar diretório privado;
2. criar o arquivo de produção;
3. aplicar permissões;
4. configurar `AGENDA_CONFIG_FILE`;
5. validar a configuração;
6. publicar o código;
7. executar migrations;
8. testar health check;
9. testar autenticação;
10. testar clima;
11. confirmar debug desativado;
12. confirmar que somente diretórios públicos são acessíveis.

O pacote de implantação não conterá o arquivo privado.

Deploys futuros não apagarão nem substituirão o diretório privado.

## cPanel e variável seletora

A forma exata de definir `AGENDA_CONFIG_FILE` dependerá da hospedagem:

- configuração no painel;
- `SetEnv` permitido;
- configuração do PHP-FPM;
- wrapper privado;
- variável explícita no cron.

A opção real será testada e documentada.

Caso a variável não seja suportada, poderá existir um pequeno seletor de implantação não versionado. Caminhos reais de conta não entrarão no Git.

## Cron

Scripts de cron usarão o mesmo loader e o mesmo arquivo.

Exemplo conceitual:

```bash
AGENDA_CONFIG_FILE=/home/CONTA/private_config/agenda-inteligente/app.php php /home/CONTA/apps/agenda-inteligente/backend/bin/cleanup-weather-cache.php
```

O comando real com caminho da conta não será versionado.

O cron não terá cópia própria de credenciais.

## Testes

Testes usarão arquivo específico, nunca produção.

Exemplo:

```text
tests/Fixtures/config/testing.php
```

Deverão cobrir:

- arquivo ausente;
- arquivo ilegível;
- retorno diferente de array;
- chaves ausentes;
- tipos incorretos;
- ambiente inválido;
- timezone inválido;
- URL inválida;
- porta inválida;
- `debug=true` em produção;
- `secure=false` em produção;
- senha vazia;
- configuração válida;
- redação de segredos;
- uso idêntico por cron.

## Rotação

A rotação será feita um segredo por vez:

1. criar nova credencial;
2. preparar novo arquivo privado;
3. validar sintaxe e permissões;
4. substituir atomicamente;
5. testar a aplicação;
6. revogar a credencial antiga;
7. monitorar erros;
8. registrar apenas a data e o tipo da rotação.

## Atualização atômica

Procedimento recomendado:

```bash
php -l app.php.new
chmod 600 app.php.new
mv app.php.new app.php
```

O arquivo temporário ficará no mesmo filesystem do definitivo.

## Backups

Backups privados:

- não entrarão no Git;
- não ficarão no `DocumentRoot`;
- terão permissões equivalentes;
- terão retenção limitada;
- serão removidos quando obsoletos.

Não serão deixados no diretório público:

```text
app.php.bak
app.php.old
app.php~
```

## Incidente de vazamento

Quando um segredo for commitado ou publicado:

1. considerar comprometido;
2. revogar ou rotacionar imediatamente;
3. remover do estado atual;
4. verificar branches, tags, PRs e artefatos;
5. avaliar limpeza do histórico;
6. invalidar builds e caches;
7. revisar logs;
8. verificar credenciais relacionadas;
9. registrar o incidente sem repetir o valor;
10. reforçar controles preventivos.

Apagar no commit seguinte não é suficiente.

Reescrever o histórico não substitui rotação.

## Banco

`Connection` continuará recebendo somente o bloco `database`.

Ela não acessará configuração global.

A mensagem pública continuará genérica:

```text
Não foi possível estabelecer conexão com o banco de dados.
```

O endpoint de saúde não retornará host, usuário, banco, DSN ou erro original do PDO.

## Sessão

O bloco de sessão continuará contendo:

```text
name
secure
same_site
idle_timeout
absolute_timeout
```

Será validado antes de `session_start()`.

ID de sessão e token CSRF são estado de execução, não configuração.

## Clima

O bloco meteorológico continuará contendo:

```text
api_key
base_url
cache_seconds
```

Poderá receber opções do ADR 0005:

```text
timeout_seconds
max_entries
stale_if_error_seconds
language
units
```

`weather.api_key` será sempre privado.

URLs públicas dos ícones não são segredos.

## URL-base e mesma origem

O frontend continuará preferindo:

```text
/auth
/api
```

`app.base_url` será usada somente quando o backend precisar de URL absoluta ou validação de origem.

Em produção, corresponderá ao domínio HTTPS oficial.

## Imutabilidade

A configuração será carregada uma vez por bootstrap.

Depois de validada:

- não será alterada por controllers;
- não será sobrescrita por parâmetros HTTP;
- não será modificada por dados do banco;
- não será persistida novamente;
- não será exposta integralmente.

## Responsabilidades

### Bootstrap

- acionar loader;
- configurar timezone e erros;
- criar dependências;
- iniciar roteamento.

### `ConfigLoader`

- resolver caminho;
- carregar arquivo;
- validar estrutura;
- validar segurança;
- devolver configuração efetiva.

### `Connection`

- receber somente bloco de banco;
- montar DSN;
- criar PDO;
- traduzir falha.

### Camadas de aplicação

- receber somente valores necessários;
- não chamar `getenv`;
- não carregar arquivo;
- não conhecer caminho privado;
- não imprimir configuração.

### Frontend

- consumir apenas valores públicos;
- usar mesma origem por padrão;
- nunca acessar segredo.

## Consequências positivas

- segredos fora do Git e do bundle;
- produção fora do diretório público;
- desenvolvimento local simples;
- testes isolados;
- ausência de dependência Dotenv;
- uma única fonte efetiva;
- precedência explícita;
- falha antecipada;
- cron e web consistentes;
- rotação e incidentes documentados.

## Consequências negativas

- preparação manual inicial;
- necessidade de configurar caminho;
- comportamento do cPanel precisa ser testado;
- validação central precisa ser implementada;
- permissões precisam ser auditadas;
- equipe deve preservar o arquivo em deploys.

## Riscos e mitigação

### Variável seletora indisponível

Mitigação: testar o provedor e usar seletor privado não versionado.

### Arquivo sobrescrito

Mitigação: produção fora do `DocumentRoot` e pacote sem configuração real.

### Permissões incorretas

Mitigação: `0600`, diretório `0700` e auditoria.

### Debug em produção

Mitigação: validação bloqueante e teste pós-deploy.

### Segredo no frontend

Mitigação: proibir `VITE_`, auditar bundle e rotacionar em incidente.

### Segredo no histórico

Mitigação: revisar stage, varrer padrões, rotacionar e limpar histórico quando necessário.

## Restrições

Esta decisão não autoriza:

- segredo em arquivo versionado;
- segredo em `VITE_*`;
- credencial real em documentação;
- senha em query string;
- log da configuração completa;
- `debug=true` em produção;
- `secure=false` em produção;
- `0777`;
- merge silencioso de múltiplas fontes;
- `getenv()` espalhado;
- Dotenv automático no backend;
- tela administrativa de segredos;
- criptografia caseira;
- segredo no health check;
- confiar somente em `.gitignore`.

## Plano de implementação

1. criar `ConfigLoader`;
2. implementar `AGENDA_CONFIG_FILE`;
3. manter fallback local;
4. validar estrutura e semântica;
5. bloquear produção insegura;
6. adaptar `bootstrap.php`;
7. revisar `app.example.php`;
8. acrescentar opções do ADR 0005;
9. criar `backend/bin/validate-config.php`;
10. criar fixtures de teste;
11. testar erros e redação;
12. documentar cPanel;
13. preparar diretório privado;
14. testar cron;
15. auditar bundle e stage;
16. validar produção.

## Comando de validação

Será criado:

```text
php backend/bin/validate-config.php
```

Ele deverá:

- usar o mesmo loader;
- não imprimir segredos;
- retornar zero em sucesso;
- retornar valor diferente de zero em falha;
- identificar apenas a chave lógica inválida;
- opcionalmente testar serviços com flag explícita.

Exemplo:

```text
Configuração válida para o ambiente production.
```

Falha:

```text
Configuração inválida: database.password está ausente.
```

O valor nunca será impresso.

## Critérios de aceite

A implementação estará concluída quando:

- produção usar arquivo fora do `DocumentRoot`;
- arquivo real não estiver no Git;
- loader estiver centralizado;
- configuração insegura falhar;
- frontend não contiver segredos;
- logs redigirem dados sensíveis;
- cron usar o mesmo loader;
- documentação de implantação existir;
- testes cobrirem falhas;
- rotação e incidente estiverem documentados.

## Critérios de revisão futura

Reavaliar quando:

- houver VPS ou contêineres;
- existir gerenciador de segredos;
- o provedor oferecer variáveis confiáveis;
- houver múltiplos servidores;
- CI/CD realizar deploy;
- rotação automática for necessária;
- auditoria exigir cofre central;
- a equipe ou integrações crescerem.

## Relação com outros documentos

Este ADR complementa:

- `docs/architecture/shared-hosting-backend-plan.md`;
- `docs/architecture/adr/0001-arquitetura-backend-php.md`;
- `docs/architecture/adr/0002-topologia-producao-mesma-origem.md`;
- `docs/architecture/adr/0003-sessao-php-e-protecao-csrf.md`;
- `docs/architecture/adr/0004-modelo-eventos-contatos.md`;
- `docs/architecture/adr/0005-cache-meteorologico.md`;
- `.gitignore`;
- `.env.example`;
- `backend/config/app.example.php`;
- `backend/bootstrap.php`;
- `backend/src/Infrastructure/Database/Connection.php`;
- `src/config/api.ts`.

## Encerramento da série

Este é o último ADR previsto no planejamento inicial.

Após sua aceitação, a fase de decisões arquiteturais iniciais estará concluída. A etapa seguinte será consolidar os ADRs no plano de implementação e corrigir incrementalmente o scaffold e as migrations.

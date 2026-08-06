# Plano de arquitetura do backend para hospedagem compartilhada

## 1. Identificação

* Projeto: Agenda Inteligente
* Repositório: `maurosakugawa/agenda-inteligente`
* Repositório-fonte: `maurosakugawa/unified-platform`
* Baseline da aplicação-fonte: `800e6bcf608496432bce413f34b4182f05e0206b`
* Backend atual da aplicação-fonte: Node.js, Express, express-session e PGlite/PostgreSQL
* Backend de destino: PHP 8.2 e MySQL/MariaDB
* Frontend preservado: React, TypeScript e Vite
* Ambiente de produção pretendido: hospedagem compartilhada com Apache, PHP e MySQL/MariaDB
* Data inicial do planejamento: 2026-08-05

## 2. Objetivo

Definir a arquitetura de destino para substituir progressivamente o backend Express/PGlite da aplicação-fonte por uma API PHP 8.2 com MySQL/MariaDB, preservando o frontend React existente e mantendo compatibilidade com hospedagem compartilhada.

Este documento transforma o inventário da API em um plano técnico de implementação e implantação.

O documento não substitui:

* `docs/migration/api-inventory.md`;
* `docs/migration/source-baseline.md`;
* `docs/security/dependency-risk-register.md`.

Esses documentos continuam sendo as referências para:

* contratos HTTP;
* comportamento da aplicação-fonte;
* rastreabilidade da migração;
* riscos conhecidos de dependências.

## 3. Escopo

O planejamento abrange:

* arquitetura do backend PHP;
* roteamento HTTP;
* autenticação baseada em sessão;
* proteção CSRF;
* acesso ao MySQL/MariaDB;
* isolamento dos dados por usuário;
* contatos;
* eventos;
* associação entre eventos e contatos;
* integração com a OpenWeather;
* cache;
* tratamento de erros;
* logs;
* configurações e segredos;
* integração com o frontend React;
* funcionamento offline e sincronização;
* tarefas periódicas compatíveis com hospedagem compartilhada;
* testes;
* implantação.

Não fazem parte desta etapa:

* implementação dos endpoints;
* criação definitiva das tabelas;
* atualização do React Router;
* alteração visual do frontend;
* inclusão de novas funcionalidades de produto;
* substituição do React por páginas PHP;
* abertura da aplicação para múltiplos ambientes de hospedagem antes da validação inicial.

## 4. Restrições do ambiente de produção

A arquitetura não pode depender de recursos normalmente indisponíveis ou inadequados em hospedagem compartilhada.

Devem ser evitados:

* processo Node.js permanentemente ativo;
* servidor Express em produção;
* servidor Django, Gunicorn ou equivalente;
* Docker em produção;
* workers permanentemente ativos;
* filas que dependam de consumidores residentes em memória;
* WebSockets hospedados pela própria aplicação;
* serviços que exijam acesso administrativo ao sistema operacional;
* banco PGlite executado como armazenamento principal da produção;
* tarefas que dependam da memória de um processo entre duas requisições.

A arquitetura poderá utilizar:

* Apache;
* PHP 8.2;
* MySQL ou MariaDB;
* arquivos estáticos;
* sessões PHP;
* cron disponibilizado pelo painel da hospedagem;
* filesystem com diretórios privados;
* HTTPS;
* chamadas HTTP externas realizadas pelo PHP;
* Composer executado localmente, com dependências enviadas junto à aplicação, caso necessário.

## 5. Princípios arquiteturais

A implementação deverá seguir os seguintes princípios:

### 5.1 Compatibilidade com hospedagem compartilhada

Toda funcionalidade necessária à produção deverá executar dentro do ciclo normal de uma requisição PHP ou de uma tarefa cron limitada.

### 5.2 Migração incremental

O backend será implementado em etapas pequenas e verificáveis.

Cada grupo de endpoints deverá ser:

1. implementado;
2. testado;
3. comparado com o contrato registrado;
4. integrado ao frontend;
5. validado antes do próximo grupo.

### 5.3 Preservação do contrato do frontend

Os caminhos, métodos, campos e formatos de resposta registrados no inventário deverão ser mantidos sempre que forem adequados.

Comportamentos incorretos ou inseguros da aplicação-fonte não deverão ser copiados automaticamente.

Exemplos:

* credenciais inválidas devem retornar `401`, e não `500`;
* usuário duplicado deve retornar `409`, e não `500`;
* recursos de outro usuário não podem ser acessados pela alteração manual de identificadores;
* erros internos não devem expor detalhes do banco ou da aplicação.

### 5.4 Separação de responsabilidades

Os arquivos de entrada HTTP não deverão concentrar:

* SQL;
* validação;
* autenticação;
* regras de negócio;
* serialização;
* integração com serviços externos;
* tratamento de erros.

A arquitetura deverá separar, no mínimo:

* camada HTTP;
* roteamento;
* middleware;
* controllers;
* services;
* repositories;
* validação;
* persistência;
* integrações externas;
* infraestrutura compartilhada.

### 5.5 Segurança por padrão

Toda decisão deverá considerar:

* autenticação;
* autorização por propriedade do recurso;
* CSRF;
* cookies seguros;
* validação de entrada;
* prepared statements;
* proteção de segredos;
* logs sem dados sensíveis;
* mensagens de erro controladas.

## 6. Arquitetura de destino

A arquitetura proposta será composta por três partes principais:

```text
Navegador
    |
    +-- Frontend React/Vite
    |       |
    |       +-- interface da aplicação
    |       +-- estado local
    |       +-- IndexedDB
    |       +-- fila de sincronização
    |
    +-- API PHP
            |
            +-- autenticação e sessão
            +-- proteção CSRF
            +-- contatos
            +-- eventos
            +-- associação evento-contato
            +-- clima
            +-- sincronização
            +-- logs e tratamento de erros
            |
            +-- MySQL/MariaDB
```

O frontend será compilado durante o processo de build:

```bash
npm run build
```

Os arquivos gerados pelo Vite serão implantados como arquivos estáticos.

O navegador não executará PHP diretamente. O frontend continuará consumindo uma API HTTP JSON.

## 7. Organização proposta do repositório

A implementação deverá evitar uma reorganização ampla e desnecessária do frontend já migrado.

A estrutura inicial proposta é:

```text
agenda-inteligente/
├── backend/
│   ├── public/
│   │   ├── index.php
│   │   └── .htaccess
│   │
│   ├── src/
│   │   ├── Auth/
│   │   ├── Contacts/
│   │   ├── Events/
│   │   ├── Weather/
│   │   ├── Sync/
│   │   └── Shared/
│   │
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   │
│   ├── routes/
│   ├── storage/
│   │   ├── cache/
│   │   └── logs/
│   │
│   ├── tests/
│   ├── bootstrap.php
│   └── composer.json
│
├── docs/
├── public/
├── src/
├── package.json
└── vite.config.ts
```

Essa estrutura é uma proposta inicial. A estrutura definitiva deverá ser validada antes da implementação.

## 8. Entrada única da API

O backend deverá utilizar um front controller:

```text
backend/public/index.php
```

Responsabilidades permitidas para o front controller:

* carregar o bootstrap;
* configurar o ambiente;
* iniciar o roteador;
* despachar a requisição;
* capturar exceções não tratadas;
* retornar a resposta HTTP.

Responsabilidades proibidas:

* executar SQL diretamente;
* conter regras de autenticação específicas;
* validar individualmente todos os endpoints;
* implementar regras de contatos, eventos ou clima.

## 9. Roteamento

O roteador deverá reconhecer método HTTP e caminho.

Exemplos:

```text
GET    /health
POST   /auth/register
POST   /auth/login
POST   /auth/logout
GET    /auth/me

GET    /api/contacts
POST   /api/contacts
PUT    /api/contacts/{id}
DELETE /api/contacts/{id}

GET    /api/events
POST   /api/events
PUT    /api/events/{id}
DELETE /api/events/{id}
GET    /api/events/{id}/contacts

GET    /api/weather/current
GET    /api/weather/forecast
GET    /api/weather/event
```

Caminhos desconhecidos sob `/api` deverão retornar:

```json
{
  "error": "Rota de API não encontrada"
}
```

com status `404`.

Caminhos desconhecidos sob `/auth` deverão retornar:

```json
{
  "error": "Rota de autenticação não encontrada"
}
```

com status `404`.

## 10. Respostas HTTP

Todas as respostas da API deverão utilizar JSON.

Cabeçalhos mínimos:

```http
Content-Type: application/json; charset=utf-8
```

Endpoints sensíveis ou dinâmicos poderão usar:

```http
Cache-Control: no-store
```

O formato básico de erro será:

```json
{
  "error": "Mensagem controlada"
}
```

A API não deverá devolver ao cliente:

* stack traces;
* caminhos internos do servidor;
* consultas SQL;
* credenciais;
* valores de configuração;
* mensagens integrais de exceções do PDO;
* conteúdo de tokens ou sessões.

## 11. Configuração

As configurações deverão ser separadas do código da aplicação.

Categorias previstas:

* ambiente;
* URL base;
* banco de dados;
* sessão;
* cookies;
* CORS, caso necessário;
* OpenWeather;
* cache;
* logs;
* limites de requisição.

Segredos não deverão ser versionados.

O repositório poderá manter:

```text
.env.example
```

com nomes das variáveis e valores ilustrativos não sensíveis.

A forma definitiva de carregamento das configurações deverá considerar as possibilidades reais da hospedagem:

1. variáveis de ambiente configuradas no servidor;
2. arquivo privado fora do diretório público;
3. arquivo PHP privado não versionado;
4. combinação controlada das opções anteriores.

A decisão deverá ser registrada antes da implantação.

## 12. Banco de dados

O banco de produção será MySQL ou MariaDB.

A comunicação deverá utilizar PDO com:

```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
PDO::ATTR_EMULATE_PREPARES => false
```

Toda entrada deverá ser transmitida por prepared statements.

Nenhum identificador de usuário recebido do frontend poderá ser utilizado como fonte de autorização.

O identificador do usuário deverá vir exclusivamente da sessão autenticada.

## 13. Modelo de dados preliminar

O modelo inicial deverá incluir, no mínimo:

* usuários;
* contatos;
* eventos;
* associação entre eventos e contatos;
* cache persistente quando necessário;
* controle de sincronização, caso adotado.

Estrutura conceitual:

```text
users
    |
    +-- contacts
    |
    +-- events
            |
            +-- event_contacts
```

A associação evento-contato deverá preferencialmente ser normalizada em tabela própria, em vez de armazenar uma lista JSON dentro da tabela de eventos.

A decisão final deverá considerar:

* compatibilidade do payload com o frontend;
* integridade referencial;
* exclusão de contatos;
* validação de propriedade;
* simplicidade das consultas;
* sincronização offline.

## 14. Migrações do banco

As alterações de banco deverão ser versionadas.

Cada migração deverá:

* possuir ordem determinística;
* ser executável uma única vez;
* registrar sua aplicação;
* evitar alterações destrutivas sem etapa de segurança;
* permitir auditoria do esquema usado em cada versão.

A implementação poderá adotar um executor próprio simples em PHP, desde que documentado e testado.

Não será obrigatória a adoção de um framework apenas para executar migrações.

## 15. Autenticação

A autenticação continuará baseada em sessão.

Após o login, a sessão deverá armazenar apenas informações necessárias, como:

```text
user_id
username
authenticated_at
last_activity
```

Não deverão ser armazenados:

* senha;
* hash de senha;
* token CSRF em logs;
* dados completos do usuário sem necessidade.

O login deverá:

1. validar o corpo da requisição;
2. localizar o usuário;
3. verificar a senha com `password_verify`;
4. regenerar o identificador da sessão;
5. gravar os dados mínimos da sessão;
6. retornar a resposta JSON.

As senhas deverão ser armazenadas com:

```php
password_hash($password, PASSWORD_DEFAULT);
```

A verificação deverá utilizar:

```php
password_verify($password, $passwordHash);
```

## 16. Configuração da sessão

A sessão deverá utilizar cookie com:

* `HttpOnly`;
* `SameSite=Lax` ou política mais restritiva compatível;
* `Secure` em produção;
* escopo de caminho adequado;
* tempo de expiração definido;
* nome próprio da aplicação.

A sessão deverá ser regenerada após login.

O logout deverá:

1. limpar os dados da sessão;
2. invalidar o cookie;
3. destruir a sessão no servidor;
4. retornar confirmação em JSON.

O endpoint `/auth/me` deverá destruir a sessão caso ela aponte para um usuário que não exista mais ou esteja indisponível.

## 17. Proteção CSRF

Como a autenticação utiliza cookie de sessão, requisições mutáveis deverão possuir proteção CSRF.

Métodos protegidos:

* `POST`;
* `PUT`;
* `PATCH`;
* `DELETE`.

Estratégia proposta:

1. o backend gera um token aleatório associado à sessão;
2. o frontend obtém o token por endpoint ou resposta autenticada;
3. o frontend envia o token em cabeçalho próprio;
4. o backend compara os valores com `hash_equals`;
5. tokens ausentes ou inválidos retornam `403`.

Cabeçalho proposto:

```http
X-CSRF-Token: <token>
```

A estratégia definitiva deverá ser implementada de forma coordenada com o frontend.

Rotas de login e registro deverão ser avaliadas separadamente quanto a CSRF, limitação de tentativas e abuso.

## 18. Autorização e isolamento por usuário

Contatos e eventos pertencem ao usuário autenticado.

Toda consulta de recurso deverá incluir simultaneamente:

```text
id do recurso
id do usuário autenticado
```

Conceitualmente:

```sql
SELECT ...
FROM contacts
WHERE id = :contact_id
  AND user_id = :authenticated_user_id
LIMIT 1
```

Não deverá existir uma etapa que:

1. busque somente pelo ID;
2. carregue um recurso de outro usuário;
3. verifique a propriedade apenas posteriormente.

Essa regra deverá ser aplicada em:

* leitura;
* atualização;
* exclusão;
* associação entre evento e contato;
* sincronização.

## 19. Validação

A validação deverá ocorrer antes do repository.

A camada de validação deverá controlar:

* campos obrigatórios;
* tipos;
* limites de comprimento;
* formato de datas;
* formato de horários;
* formato de e-mail;
* valores permitidos;
* arrays;
* identificadores;
* parâmetros de consulta.

A aplicação não deverá depender exclusivamente de atributos HTML ou validações executadas no frontend.

## 20. Contatos

O módulo de contatos deverá implementar:

* listagem do usuário autenticado;
* criação;
* atualização completa;
* exclusão;
* validação;
* ordenação por criação decrescente;
* isolamento por usuário.

A estrutura externa deverá permanecer compatível com o contrato registrado.

Antes da implementação deverão ser definidos os limites dos campos:

* nome;
* telefone;
* e-mail;
* CEP;
* logradouro;
* número;
* bairro;
* cidade;
* UF.

## 21. Eventos

O módulo de eventos deverá implementar:

* listagem;
* filtros por período;
* filtro por categoria;
* criação;
* atualização;
* exclusão;
* ordenação por data e horário;
* associação com contatos;
* lembrete em minutos;
* isolamento por usuário.

Valores padrão previstos:

```text
category = geral
priority = media
contact_ids = []
reminder_minutes = 0
```

Esses valores deverão ser validados contra o comportamento esperado do frontend antes da implementação definitiva.

## 22. Associação entre eventos e contatos

A associação deverá garantir que:

* o evento pertença ao usuário autenticado;
* todos os contatos informados pertençam ao mesmo usuário;
* IDs duplicados sejam tratados;
* contatos inexistentes sejam rejeitados ou ignorados conforme decisão documentada;
* atualizações ocorram de forma transacional;
* a resposta permaneça compatível com o campo `contact_ids`.

A implementação proposta utiliza uma tabela de associação:

```text
event_contacts
```

A criação ou atualização de evento deverá usar transação quando modificar simultaneamente:

* o evento;
* seus contatos associados.

## 23. Integração com a OpenWeather

A chave da OpenWeather deverá permanecer exclusivamente no backend.

O frontend nunca deverá receber:

* chave da API;
* arquivo de configuração privado;
* URL contendo segredo;
* detalhes internos de falha da integração.

O módulo deverá preservar os comportamentos registrados:

* validação de cidade;
* unidades métricas;
* respostas em português;
* timeout;
* distinção entre erro do usuário e erro do serviço externo;
* seleção de previsão para eventos;
* limite da janela de previsão;
* resposta para eventos passados.

## 24. Cache meteorológico

O cache em memória do backend Node não poderá ser reproduzido da mesma forma, pois cada requisição PHP possui ciclo independente.

Alternativas compatíveis:

1. cache em arquivos;
2. cache em tabela MySQL;
3. cache fornecido pela hospedagem, quando disponível.

A estratégia inicial recomendada é um cache persistente simples, com:

* chave normalizada por operação e cidade;
* conteúdo JSON;
* data de criação;
* data de expiração;
* exclusão periódica de entradas vencidas;
* limite de tamanho ou quantidade.

A decisão entre arquivo e banco deverá considerar:

* permissões do filesystem;
* concorrência;
* facilidade de limpeza;
* volume esperado;
* impacto no banco;
* recursos reais da hospedagem.

## 25. Funcionamento offline

O frontend migrado já possui serviços relacionados a:

* IndexedDB;
* localStorage;
* fila;
* sincronização.

A implementação do backend não deverá remover o funcionamento local antes da definição da estratégia de sincronização.

Deverão ser analisados:

* origem da verdade dos dados;
* geração de identificadores;
* criação offline;
* atualização concorrente;
* exclusão offline;
* repetição de requisições;
* idempotência;
* conflitos;
* estado da fila;
* comportamento após expiração da sessão.

A primeira versão do backend poderá priorizar operações online, desde que:

* não destrua a infraestrutura offline existente;
* não apresente sincronização incompleta como concluída;
* registre claramente as limitações temporárias.

## 26. Sincronização

A sincronização deverá ser tratada como módulo próprio.

Possíveis requisitos:

* identificador local;
* identificador do servidor;
* versão ou timestamp;
* estado da operação;
* tentativas;
* última mensagem de erro;
* idempotency key;
* marcação de exclusão;
* resolução de conflito.

A estratégia definitiva deverá ser planejada depois da implementação e validação dos módulos CRUD básicos.

Não se deve misturar a primeira implementação dos endpoints com uma sincronização complexa ainda não especificada.

## 27. Lembretes e tarefas periódicas

Workers permanentes não serão utilizados.

Tipos de lembrete deverão ser separados:

### 27.1 Lembretes enquanto a aplicação está aberta

Podem continuar sendo processados no frontend, conforme os hooks e serviços existentes.

### 27.2 Lembretes do navegador

Poderão utilizar APIs do navegador quando suportadas e autorizadas pelo usuário.

### 27.3 Lembretes independentes do navegador aberto

Exigirão solução adicional, como:

* cron;
* e-mail;
* serviço externo de notificações;
* push notification compatível;
* outra integração futura.

A primeira versão do backend não deverá prometer notificações independentes do navegador sem uma implementação verificável.

## 28. Cron

Caso sejam necessárias tarefas periódicas, elas deverão ser executadas por scripts CLI PHP acionados pelo cron da hospedagem.

Exemplos:

```text
backend/bin/cleanup-cache.php
backend/bin/process-reminders.php
backend/bin/cleanup-sessions.php
```

Os scripts deverão:

* impedir acesso HTTP;
* utilizar bloqueio contra execução simultânea quando necessário;
* limitar o volume processado por execução;
* registrar erros;
* retornar códigos de saída;
* ser idempotentes sempre que possível.

## 29. Tratamento global de erros

A API deverá possuir tratamento centralizado.

Categorias previstas:

* erro de validação: `400` ou `422`, conforme decisão;
* autenticação ausente ou inválida: `401`;
* CSRF inválido ou acesso proibido: `403`;
* recurso inexistente: `404`;
* conflito: `409`;
* falha de serviço externo: `502`;
* serviço temporariamente indisponível: `503`;
* timeout externo: `504`;
* erro interno não previsto: `500`.

A mensagem externa deverá ser controlada.

O detalhe técnico deverá ser enviado apenas ao log privado.

## 30. Logs

Os logs deverão ficar fora do diretório público sempre que possível.

Os registros deverão conter, quando aplicável:

* timestamp;
* nível;
* identificador da requisição;
* método;
* rota;
* status;
* usuário autenticado, sem dados desnecessários;
* categoria do erro;
* mensagem técnica controlada.

Não deverão ser registrados:

* senha;
* hash de senha;
* cookie de sessão;
* token CSRF;
* chave da OpenWeather;
* conteúdo integral de cabeçalhos sensíveis;
* dados pessoais sem necessidade.

## 31. Limitação de requisições

A aplicação deverá prever proteção contra abuso, principalmente em:

* registro;
* login;
* consultas meteorológicas;
* sincronização.

Em hospedagem compartilhada, a primeira implementação poderá utilizar persistência em banco ou arquivos.

A estratégia definitiva deverá considerar:

* IP;
* usuário;
* janela de tempo;
* quantidade de tentativas;
* expiração;
* limpeza das entradas;
* impacto em usuários legítimos.

## 32. CORS e topologia de implantação

A preferência será servir frontend e API sob a mesma origem.

Exemplo:

```text
https://agenda.exemplo.com/
https://agenda.exemplo.com/api/
https://agenda.exemplo.com/auth/
```

Vantagens:

* menor complexidade de CORS;
* cookies de sessão mais simples;
* proteção CSRF mais previsível;
* implantação mais direta;
* menos diferenças entre desenvolvimento e produção.

Caso frontend e API sejam servidos em origens diferentes, será necessário definir explicitamente:

* origem permitida;
* credenciais CORS;
* cookies;
* `SameSite`;
* HTTPS;
* preflight;
* política de desenvolvimento e produção.

## 33. Integração com o frontend

A configuração da API deverá continuar centralizada.

O frontend não deverá espalhar URLs fixas pelos módulos.

Deverá existir uma origem configurável para:

* desenvolvimento;
* testes;
* homologação;
* produção.

Todas as requisições autenticadas deverão enviar cookies.

Requisições mutáveis também deverão enviar o token CSRF.

## 34. Regras do Apache

O Apache deverá distinguir:

* arquivos estáticos existentes;
* rotas da API;
* rotas de autenticação;
* fallback da SPA.

As regras deverão evitar que:

* uma rota `/api` inexistente retorne o `index.html`;
* uma rota React válida retorne `404` do Apache;
* arquivos privados sejam servidos;
* diretórios internos do backend fiquem acessíveis.

A configuração definitiva dependerá da estrutura real usada na hospedagem.

## 35. Diretório público

Somente arquivos necessários ao atendimento HTTP deverão ficar diretamente acessíveis.

Itens que não devem ficar públicos:

* configurações privadas;
* migrations;
* logs;
* testes;
* código interno;
* arquivos de cache;
* scripts cron;
* dumps;
* backups;
* arquivos `.env`;
* documentação interna sensível.

Quando a hospedagem não permitir apontar o document root diretamente para o diretório público, deverão ser adotadas regras adicionais de proteção e organização.

## 36. Dependências PHP

A preferência será utilizar a menor quantidade possível de dependências externas.

Uma dependência só deverá ser adicionada quando:

* reduzir risco;
* evitar implementação insegura;
* possuir manutenção ativa;
* funcionar em PHP 8.2;
* ser implantável sem comandos no servidor;
* possuir licença compatível.

O backend não deverá adotar um framework completo sem uma justificativa arquitetural clara.

A decisão sobre framework ou implementação enxuta deverá ser registrada antes da primeira branch de código.

## 37. Testes

A estratégia deverá incluir:

### 37.1 Testes unitários

Para:

* validação;
* regras de negócio;
* normalização;
* seleção de previsão;
* serviços puros.

### 37.2 Testes de integração

Para:

* repositories;
* transações;
* autenticação;
* sessão;
* isolamento por usuário;
* associação evento-contato.

### 37.3 Testes de contrato

Para conferir:

* método;
* caminho;
* status;
* formato JSON;
* campos obrigatórios;
* respostas de erro;
* autenticação;
* ordenação;
* filtros.

### 37.4 Testes manuais de frontend

Para:

* registro;
* login;
* logout;
* rotas protegidas;
* contatos;
* eventos;
* clima;
* falha de sessão;
* funcionamento offline;
* sincronização.

## 38. Ambientes

Deverão ser diferenciados, no mínimo:

* desenvolvimento;
* teste;
* produção.

Cada ambiente deverá possuir:

* banco separado;
* configurações próprias;
* chaves próprias;
* nível de log adequado;
* política de cookies correspondente;
* URL base própria.

Produção deverá obrigatoriamente utilizar HTTPS para cookies `Secure`.

## 39. Implantação

A implantação deverá ser reproduzível.

Fluxo preliminar:

1. instalar dependências do frontend localmente;
2. executar lint e testes;
3. compilar o frontend;
4. instalar dependências PHP localmente, quando houver;
5. executar testes do backend;
6. gerar o pacote de implantação;
7. realizar backup;
8. enviar arquivos;
9. configurar segredos;
10. executar migrações;
11. verificar permissões;
12. testar `/health`;
13. testar autenticação;
14. executar smoke tests;
15. acompanhar logs.

## 40. Rollback

Toda implantação deverá possuir plano de retorno.

O rollback poderá exigir:

* restauração dos arquivos anteriores;
* reversão de configuração;
* restauração do banco;
* desativação temporária de endpoints;
* retorno do frontend a uma versão compatível.

Migrações destrutivas deverão ser evitadas até que exista uma estratégia segura de rollback de dados.

## 41. Fases propostas de implementação

### Fase 1 — Fundação do backend

* estrutura de diretórios;
* bootstrap;
* configuração;
* PDO;
* roteador;
* request e response;
* tratamento global de erros;
* `/health`.

### Fase 2 — Autenticação e segurança de sessão

* usuários;
* registro;
* login;
* logout;
* `/auth/me`;
* middleware de autenticação;
* cookies;
* sessão;
* proteção inicial contra abuso.

### Fase 3 — CSRF e integração básica com o frontend

* geração de token;
* validação;
* cabeçalho;
* integração com o cliente HTTP;
* testes das rotas mutáveis.

### Fase 4 — Contatos

* esquema;
* repository;
* service;
* controller;
* validação;
* endpoints;
* testes;
* integração com o frontend.

### Fase 5 — Eventos

* esquema;
* repository;
* service;
* controller;
* filtros;
* endpoints;
* testes;
* integração com o frontend.

### Fase 6 — Associação evento-contato

* tabela de associação;
* transações;
* validação de propriedade;
* resposta `contact_ids`;
* endpoint de contatos do evento.

### Fase 7 — Clima

* cliente HTTP;
* configuração da chave;
* cache;
* timeout;
* normalização;
* tratamento de erros;
* endpoints;
* testes.

### Fase 8 — Sincronização

* análise do mecanismo atual;
* protocolo;
* idempotência;
* fila;
* conflitos;
* integração com IndexedDB.

### Fase 9 — Lembretes e tarefas periódicas

* definição dos canais;
* scripts cron;
* limpeza de cache;
* processamento limitado;
* logs.

### Fase 10 — Preparação de produção

* `.htaccess`;
* pacote de build;
* configuração;
* migrations;
* smoke tests;
* documentação de implantação;
* rollback.

## 42. Decisões arquiteturais pendentes

Antes da implementação deverão ser decididos:

1. backend PHP enxuto ou microframework;
2. estrutura definitiva de diretórios;
3. estratégia de carregamento de configurações;
4. nome e duração do cookie de sessão;
5. armazenamento da sessão;
6. política CSRF;
7. código `400` ou `422` para validação semântica;
8. tabela de associação evento-contato;
9. estratégia de cache meteorológico;
10. mecanismo de migrations;
11. estratégia de rate limiting;
12. topologia de produção;
13. comportamento inicial da sincronização;
14. escopo real dos lembretes;
15. estratégia de logs e rotação;
16. procedimento de implantação;
17. procedimento de rollback.

Cada decisão relevante deverá ser registrada por documentação ou ADR.

## 43. Critérios de aceite do planejamento

Este planejamento poderá ser considerado concluído quando:

* a arquitetura de destino estiver documentada;
* as restrições da hospedagem estiverem registradas;
* o frontend preservado estiver claramente separado do backend;
* os módulos do backend estiverem delimitados;
* a estratégia de sessão estiver definida;
* a estratégia CSRF estiver definida;
* o modelo de dados inicial estiver definido;
* a associação evento-contato estiver definida;
* a estratégia de cache estiver definida;
* a estratégia de configuração e segredos estiver definida;
* a ordem de implementação estiver aprovada;
* as decisões pendentes estiverem resolvidas ou encaminhadas;
* os critérios de testes estiverem documentados;
* o processo de implantação estiver documentado;
* o plano de rollback estiver documentado.

## 44. Próxima etapa

Após a aprovação deste documento, deverão ser produzidas as decisões arquiteturais específicas.

Ordem sugerida:

1. ADR da arquitetura PHP;
2. ADR da topologia de produção;
3. ADR de sessão e CSRF;
4. ADR do modelo de dados;
5. ADR do cache meteorológico;
6. plano de testes de contrato;
7. plano de implantação.

Somente depois dessas decisões deverá ser criada a primeira branch de implementação do backend PHP.

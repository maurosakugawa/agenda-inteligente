# ADR 0001 — Arquitetura do backend PHP

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente utiliza atualmente um frontend construído com React, TypeScript e Vite.

A aplicação-fonte utiliza um backend baseado em:

* Node.js;
* Express;
* express-session;
* PGlite/PostgreSQL.

O ambiente de produção pretendido é uma hospedagem compartilhada com:

* Apache;
* PHP 8.2;
* MySQL ou MariaDB;
* acesso limitado ao sistema operacional;
* ausência de processos permanentemente ativos;
* ausência de Docker em produção;
* ausência de um servidor Node.js residente.

O inventário atual registra 17 endpoints distribuídos entre:

* saúde da aplicação;
* autenticação;
* contatos;
* eventos;
* associação entre eventos e contatos;
* integração meteorológica.

A arquitetura de destino precisa ser:

* compatível com hospedagem compartilhada;
* simples de implantar;
* segura;
* testável;
* modular;
* adequada ao tamanho atual da aplicação;
* capaz de preservar o contrato HTTP consumido pelo frontend.

## Problema

É necessário escolher a estrutura do backend PHP.

As principais alternativas consideradas são:

1. PHP procedural concentrado em arquivos de endpoint;
2. PHP modular próprio com Composer e PSR-4;
3. microframework PHP;
4. framework PHP completo.

## Critérios de decisão

A solução deve considerar:

* quantidade atual de endpoints;
* complexidade do domínio;
* facilidade de implantação em hospedagem compartilhada;
* consumo de recursos;
* segurança;
* separação de responsabilidades;
* testabilidade;
* manutenção;
* quantidade de dependências;
* curva de aprendizado;
* possibilidade de evolução futura;
* preservação do frontend React.

## Alternativa 1 — PHP procedural por endpoint

Exemplo conceitual:

```text
api/
├── contacts-list.php
├── contacts-create.php
├── events-list.php
├── events-create.php
└── weather-current.php
```

### Vantagens

* implantação simples;
* baixo número inicial de arquivos de infraestrutura;
* execução direta pelo Apache;
* ausência de dependências obrigatórias.

### Desvantagens

* duplicação de autenticação e validação;
* risco de SQL espalhado;
* tratamento de erros inconsistente;
* dificuldade para aplicar middleware;
* dificuldade para testar regras isoladamente;
* tendência de misturar HTTP, regras de negócio e persistência;
* crescimento desorganizado;
* maior risco de violações de DRY e SRP.

### Avaliação

Não recomendada.

A simplicidade inicial não compensa os riscos de manutenção e segurança.

## Alternativa 2 — PHP modular próprio com Composer e PSR-4

A aplicação utilizaria:

* um front controller;
* um roteador HTTP;
* autoload PSR-4;
* controllers;
* services;
* repositories;
* middlewares;
* validação;
* objetos ou estruturas de request e response;
* PDO para persistência.

Fluxo conceitual:

```text
Request
   |
   v
Front Controller
   |
   v
Router
   |
   v
Middleware
   |
   v
Controller
   |
   v
Service
   |
   v
Repository
   |
   v
PDO / MySQL
```

### Vantagens

* controle sobre a arquitetura;
* baixo número de dependências;
* implantação compatível com hospedagem compartilhada;
* separação de responsabilidades;
* facilidade para aplicar autenticação e CSRF;
* repositories reutilizáveis;
* regras de negócio testáveis;
* menor sobrecarga que um framework completo;
* possibilidade de evolução incremental;
* estrutura adequada aos 17 endpoints conhecidos.

### Desvantagens

* necessidade de implementar a infraestrutura inicial;
* risco de recriar inadequadamente funcionalidades já resolvidas por bibliotecas;
* necessidade de documentar convenções próprias;
* necessidade de manter o roteador e o tratamento HTTP;
* disciplina arquitetural obrigatória.

### Avaliação

Recomendada.

A alternativa oferece equilíbrio entre simplicidade operacional e organização interna.

## Alternativa 3 — Microframework PHP

Poderia ser utilizado um microframework para fornecer:

* roteamento;
* middlewares;
* request e response;
* tratamento de erros;
* integração com contêiner de dependências.

### Vantagens

* reduz a quantidade de infraestrutura própria;
* oferece convenções conhecidas;
* facilita roteamento e middleware;
* pode melhorar a testabilidade.

### Desvantagens

* adiciona dependências;
* cria vínculo com uma biblioteca específica;
* pode trazer recursos desnecessários;
* exige avaliação de compatibilidade com a hospedagem;
* pode aumentar a complexidade do pacote de implantação;
* não elimina a necessidade de definir arquitetura interna.

### Avaliação

Alternativa válida, mas não necessária para a primeira implementação.

Poderá ser reconsiderada caso a infraestrutura própria cresça além do previsto.

## Alternativa 4 — Framework PHP completo

Exemplos de capacidades geralmente fornecidas:

* roteamento;
* ORM;
* migrations;
* filas;
* eventos;
* autenticação;
* contêiner;
* CLI;
* cache;
* templates;
* ferramentas de desenvolvimento.

### Vantagens

* ecossistema amplo;
* convenções maduras;
* recursos integrados;
* documentação extensa;
* estrutura conhecida por outros desenvolvedores.

### Desvantagens

* maior quantidade de dependências;
* maior custo de implantação;
* funcionalidades desnecessárias para o escopo atual;
* possível dependência de comandos no servidor;
* maior consumo de recursos;
* curva de aprendizado;
* risco de adaptar o projeto às convenções do framework em vez de preservar o contrato existente;
* complexidade desproporcional aos 17 endpoints conhecidos.

### Avaliação

Não recomendada para a primeira versão do backend.

Poderá ser reavaliada caso o domínio e o número de módulos cresçam substancialmente.

## Decisão

Adotar um backend PHP modular próprio, utilizando:

* PHP 8.2;
* Composer;
* autoload PSR-4;
* front controller;
* roteador HTTP enxuto;
* middleware;
* controllers;
* services;
* repositories;
* PDO;
* MySQL ou MariaDB;
* respostas JSON;
* tratamento centralizado de erros.

Não será adotado um framework PHP completo nesta etapa.

## Estrutura conceitual

```text
backend/
├── public/
│   ├── index.php
│   └── .htaccess
├── src/
│   ├── Auth/
│   ├── Contacts/
│   ├── Events/
│   ├── Weather/
│   ├── Sync/
│   └── Shared/
├── config/
├── database/
├── routes/
├── storage/
├── tests/
├── bootstrap.php
└── composer.json
```

## Responsabilidades

### Front controller

Responsável somente por:

* carregar o bootstrap;
* receber a requisição;
* iniciar o roteador;
* despachar a rota;
* capturar erros não tratados;
* enviar a resposta.

### Router

Responsável por:

* reconhecer o método HTTP;
* reconhecer o caminho;
* extrair parâmetros de rota;
* selecionar middlewares;
* despachar o controller.

### Middleware

Responsável por aspectos transversais, como:

* autenticação;
* CSRF;
* limite de requisições;
* cabeçalhos;
* identificação da requisição.

### Controller

Responsável por:

* receber dados HTTP;
* solicitar validação;
* chamar o service;
* transformar o resultado em resposta HTTP.

O controller não deverá conter SQL.

### Service

Responsável por:

* regras de negócio;
* coordenação de operações;
* transações;
* validação de propriedade;
* integração entre repositories.

O service não deverá produzir diretamente respostas HTTP.

### Repository

Responsável por:

* consultas SQL;
* prepared statements;
* persistência;
* transformação entre registros do banco e estruturas internas.

O repository não deverá decidir status HTTP.

## Dependências

Dependências externas deverão ser adicionadas somente quando:

* reduzirem risco;
* evitarem implementação insegura;
* forem compatíveis com PHP 8.2;
* possuírem manutenção ativa;
* forem implantáveis sem execução de comandos no servidor de produção;
* tiverem finalidade claramente documentada.

O Composer poderá ser executado localmente.

O diretório `vendor` necessário à produção poderá ser incluído no pacote de implantação, sem exigir Composer disponível na hospedagem.

## Consequências positivas

* compatibilidade com hospedagem compartilhada;
* baixo acoplamento com frameworks;
* separação entre HTTP, regras e SQL;
* facilidade para testes;
* controle sobre o contrato da API;
* evolução incremental;
* dependências reduzidas;
* implantação previsível;
* possibilidade de substituir componentes isolados futuramente.

## Consequências negativas

* a equipe será responsável pela infraestrutura própria;
* convenções precisarão ser documentadas;
* roteamento e respostas precisarão de testes;
* existe risco de crescimento indevido da camada compartilhada;
* revisões deverão impedir controllers ou services excessivamente grandes;
* funcionalidades maduras não deverão ser reimplementadas de maneira insegura.

## Restrições

A decisão não autoriza:

* criar um framework interno genérico;
* concentrar toda a aplicação no front controller;
* executar SQL em controllers;
* duplicar autenticação em cada endpoint;
* acoplar services a variáveis globais;
* expor exceções técnicas ao frontend;
* abandonar o contrato registrado no inventário da API;
* reescrever o frontend React em PHP.

## Critérios de revisão futura

A decisão deverá ser reavaliada caso:

* o número de módulos cresça substancialmente;
* o backend passe a exigir filas complexas;
* surjam múltiplas integrações externas;
* a infraestrutura própria se torne maior que o domínio;
* a equipe aumente;
* a aplicação deixe a hospedagem compartilhada;
* um microframework reduza claramente o custo de manutenção;
* requisitos futuros exijam recursos não justificáveis em implementação própria.

## Relação com outros documentos

Este ADR complementa:

* `docs/architecture/shared-hosting-backend-plan.md`;
* `docs/migration/api-inventory.md`;
* `docs/migration/source-baseline.md`;
* `docs/security/dependency-risk-register.md`.

## Próximas decisões

Após a aceitação deste ADR, deverão ser registrados:

1. topologia de produção e mesma origem;
2. sessão e proteção CSRF;
3. modelo de dados de eventos e contatos;
4. cache meteorológico;
5. estratégia de configuração e segredos.

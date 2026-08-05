# Registro de riscos de dependências

## GHSA-qwww-vcr4-c8h2 — React Router

### Dependências identificadas

- react-router-dom: 7.18.2
- react-router: 7.18.2

### Resultado da auditoria

O `npm audit` classifica a dependência como vulnerável por ela
pertencer à faixa indicada pela advisory GHSA-qwww-vcr4-c8h2.

### Avaliação de aplicabilidade

A Agenda Inteligente é uma SPA construída com React e Vite.

A pesquisa no código não encontrou uso de:

- React Server Components;
- React Router Framework Mode;
- RSCStaticRouter;
- matchRSC;
- routeRSC;
- arquivos entry.rsc;
- APIs unstable relacionadas a RSC;
- servidor React Router.

A aplicação utiliza o React Router apenas para navegação client-side.

### Decisão

A versão 7.18.2 será mantida temporariamente durante a migração
do backend Express/PGlite para PHP/MySQL.

Não será utilizado `npm audit fix --force`, pois o comando propõe
uma alteração de dependência sem garantia de compatibilidade com
o frontend atual.

A migração para React Router 8.3.0 ou superior será tratada em
branch própria, com testes de navegação, autenticação, redirecionamento
e rotas protegidas.

### Mitigações

- não introduzir React Server Components;
- não introduzir React Router Framework Mode;
- manter autenticação e operações mutáveis no backend PHP;
- aplicar proteção CSRF no backend PHP;
- manter o package-lock.json versionado;
- revisar a advisory durante atualizações de dependências.

### Data da avaliação

2026-08-05

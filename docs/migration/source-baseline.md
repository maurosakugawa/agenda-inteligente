# Baseline da aplicação-fonte

## Repositório de origem

- Repositório: `maurosakugawa/unified-platform`
- Branch de origem: `main`
- Commit de referência: `800e6bcf608496432bce413f34b4182f05e0206b`

## Repositório de destino

- Repositório: `maurosakugawa/agenda-inteligente`
- Branch de migração: `refactor/migrar-frontend-e-inventariar-api`

## Objetivo

Preservar o frontend React, TypeScript e Vite da Plataforma Unificada e
substituir progressivamente o backend Express/PGlite por PHP 8.2 e
MySQL/MariaDB, mantendo compatibilidade com hospedagem compartilhada.

## Regra de rastreabilidade

As comparações iniciais de comportamento, endpoints, payloads, banco de dados
e estrutura devem utilizar o commit de referência registrado neste documento.

Mudanças posteriores no repositório `unified-platform` não serão incorporadas
automaticamente. Qualquer atualização da baseline deverá ser deliberada,
documentada e submetida a revisão.

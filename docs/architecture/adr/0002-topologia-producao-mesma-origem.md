# ADR 0002 — Topologia de produção na mesma origem

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente possui:

- frontend React, TypeScript e Vite;
- backend de destino em PHP 8.2;
- API HTTP com respostas JSON;
- autenticação baseada em sessão;
- proteção CSRF;
- implantação prevista em hospedagem compartilhada com Apache;
- banco MySQL ou MariaDB.

O frontend será compilado pelo Vite e implantado como arquivos estáticos.

O backend PHP será executado pelo Apache dentro do ciclo normal das requisições HTTP.

É necessário decidir como frontend e backend serão expostos em produção.

## Problema

As principais opções são:

1. frontend e backend sob a mesma origem;
2. frontend no domínio principal e API em um subdomínio;
3. frontend e backend em origens completamente separadas.

A escolha afeta:

- cookies;
- sessões;
- CORS;
- CSRF;
- regras do Apache;
- implantação;
- desenvolvimento local;
- segurança;
- complexidade operacional.

## Definições

Uma origem é composta por:

```text
protocolo + domínio + porta
```

Exemplos que pertencem à mesma origem:

```text
https://agenda.exemplo.com/
https://agenda.exemplo.com/api/events
https://agenda.exemplo.com/auth/login
```

Exemplos de origens diferentes:

```text
https://agenda.exemplo.com/
https://api.agenda.exemplo.com/
```

Mesmo sendo subdomínios relacionados, os endereços possuem origens diferentes.

## Critérios de decisão

A topologia deverá priorizar:

- compatibilidade com hospedagem compartilhada;
- simplicidade de implantação;
- segurança dos cookies;
- uso de sessão PHP;
- proteção CSRF previsível;
- redução da configuração CORS;
- menor quantidade de diferenças entre ambientes;
- preservação das rotas da SPA;
- isolamento dos arquivos privados do backend;
- facilidade de diagnóstico.

## Alternativa 1 — Frontend e backend na mesma origem

Exemplo:

```text
https://agenda.exemplo.com/
https://agenda.exemplo.com/api/
https://agenda.exemplo.com/auth/
https://agenda.exemplo.com/health
```

O frontend React é servido na raiz.

As requisições iniciadas com `/api`, `/auth` e `/health` são direcionadas ao backend PHP.

As demais rotas não correspondentes a arquivos existentes podem utilizar o fallback da SPA.

### Vantagens

- dispensa CORS em produção;
- simplifica cookies de sessão;
- simplifica proteção CSRF;
- reduz preflight;
- reduz configurações específicas de credenciais;
- permite URLs relativas no frontend;
- facilita implantação em uma única conta de hospedagem;
- reduz dependência de DNS adicional;
- diminui diferenças entre frontend e backend;
- simplifica testes manuais;
- reduz pontos de falha.

### Desvantagens

- exige regras cuidadosas no Apache;
- API e SPA compartilham o mesmo domínio;
- uma configuração incorreta de rewrite pode enviar rotas da API para o `index.html`;
- a estrutura pública precisa proteger os arquivos internos do backend;
- uma indisponibilidade do domínio afeta frontend e API simultaneamente.

### Avaliação

Recomendada.

É a alternativa mais simples e compatível com o ambiente pretendido.

## Alternativa 2 — API em subdomínio

Exemplo:

```text
https://agenda.exemplo.com/
https://api.agenda.exemplo.com/
```

### Vantagens

- separação explícita entre frontend e backend;
- possibilidade de configurações independentes;
- facilita uma futura migração da API para outra infraestrutura;
- pode permitir políticas próprias de cache e servidor.

### Desvantagens

- exige configuração CORS;
- exige envio explícito de credenciais;
- aumenta a complexidade de cookies;
- exige avaliação de `SameSite`, domínio e segurança;
- pode gerar requisições preflight;
- exige DNS e configuração adicional;
- aumenta as diferenças entre desenvolvimento e produção;
- não oferece benefício proporcional para o escopo atual.

### Avaliação

Não recomendada para a primeira implantação.

Poderá ser reconsiderada caso frontend e API sejam hospedados em infraestruturas diferentes.

## Alternativa 3 — Origens completamente separadas

Exemplo:

```text
https://aplicacao.exemplo.com/
https://servico-api-outro-dominio.com/
```

### Vantagens

- independência completa de infraestrutura;
- possibilidade de implantação e escalabilidade separadas;
- separação operacional clara.

### Desvantagens

- configuração CORS obrigatória;
- maior complexidade de sessão;
- maior complexidade de CSRF;
- dependência entre domínios;
- aumento da superfície de configuração;
- mais riscos de erros de credenciais e cookies;
- implantação desproporcional ao escopo;
- incompatibilidade conceitual com a simplicidade buscada na hospedagem compartilhada.

### Avaliação

Não recomendada.

## Decisão

Servir o frontend React e o backend PHP sob a mesma origem.

Topologia conceitual:

```text
https://agenda.exemplo.com/
├── arquivos estáticos da SPA
├── /api/*
├── /auth/*
└── /health
```

Rotas reservadas ao backend:

```text
/api/*
/auth/*
/health
```

As rotas reservadas ao backend nunca deverão utilizar o fallback da SPA.

## URLs utilizadas pelo frontend

O frontend deverá preferir URLs relativas:

```text
/auth/login
/auth/logout
/auth/me
/api/contacts
/api/events
/api/weather/current
```

Não deverão ser espalhadas URLs completas pelos componentes ou stores.

A configuração da origem da API deverá permanecer centralizada para permitir desenvolvimento, testes e uma eventual mudança futura de topologia.

## Ordem conceitual do roteamento Apache

O Apache deverá avaliar as requisições na seguinte ordem:

1. arquivos e diretórios públicos realmente existentes;
2. rotas reservadas ao backend;
3. rotas do frontend React;
4. respostas `404` apropriadas.

Exemplo conceitual:

```text
Requisição
    |
    +-- Arquivo público existente? --> servir arquivo
    |
    +-- /api/*, /auth/* ou /health? --> backend PHP
    |
    +-- Rota da SPA? --> index.html
    |
    +-- Caso restante --> 404
```

A configuração definitiva será produzida durante a preparação da implantação.

## Tratamento de rotas desconhecidas

Uma rota desconhecida sob `/api` deverá retornar JSON com status `404`.

Exemplo:

```json
{
  "error": "Rota de API não encontrada"
}
```

Uma rota desconhecida sob `/auth` também deverá retornar JSON com status `404`.

Ela não poderá retornar:

- o HTML da SPA;
- uma página padrão do Apache;
- detalhes internos do servidor.

Rotas válidas do React deverão retornar o `index.html`, permitindo que o React Router resolva a navegação no navegador.

## Cookies

A autenticação utilizará cookie de sessão associado à mesma origem.

Em produção, o cookie deverá possuir:

- `HttpOnly`;
- `Secure`;
- `SameSite=Lax` ou política mais restritiva compatível;
- caminho apropriado;
- nome específico da aplicação.

O cookie não deverá ser acessível pelo JavaScript do frontend.

A mesma origem simplifica o envio automático do cookie nas requisições à API.

## CSRF

A mesma origem não elimina a necessidade de proteção CSRF.

As requisições mutáveis continuarão exigindo token CSRF.

Métodos protegidos:

- `POST`;
- `PUT`;
- `PATCH`;
- `DELETE`.

O frontend deverá enviar o token em cabeçalho próprio, conforme decisão específica de sessão e CSRF.

## CORS

A produção na mesma origem não deverá habilitar CORS de maneira ampla.

Não deverão ser utilizados cabeçalhos como:

```http
Access-Control-Allow-Origin: *
```

em endpoints autenticados.

Caso uma origem adicional seja necessária futuramente, ela deverá ser:

- explicitamente registrada;
- restrita;
- validada;
- documentada;
- testada com cookies e CSRF.

## Desenvolvimento local

O desenvolvimento poderá utilizar processos separados, por exemplo:

```text
Frontend Vite: http://localhost:5173
Backend PHP:   http://localhost:8000
```

A preferência será configurar proxy no Vite:

```text
/auth/*   -> backend PHP
/api/*    -> backend PHP
/health   -> backend PHP
```

Assim, o navegador continuará consumindo caminhos relativos e o comportamento ficará próximo da mesma origem utilizada em produção.

O proxy de desenvolvimento não fará parte do pacote de produção.

## Estrutura pública

A implantação deverá garantir que somente arquivos públicos sejam acessíveis pelo Apache.

Não deverão ser servidos diretamente:

- código interno do backend;
- configurações;
- migrations;
- logs;
- cache;
- testes;
- scripts cron;
- arquivos `.env`;
- dumps;
- backups;
- documentação sensível.

Quando a hospedagem não permitir alterar o document root, deverão ser utilizadas organização de diretórios e regras de bloqueio adicionais.

## Consequências positivas

- produção sem CORS para o fluxo normal;
- cookies mais simples;
- sessão PHP mais previsível;
- menor complexidade de CSRF;
- URLs relativas;
- menos configuração;
- implantação em uma única origem;
- diagnóstico mais direto;
- menor risco de divergência entre domínios;
- compatibilidade com hospedagem compartilhada.

## Consequências negativas

- necessidade de regras precisas no Apache;
- necessidade de proteger diretórios internos;
- frontend e API compartilham indisponibilidade do domínio;
- futura separação da API exigirá revisão de CORS, cookies e configuração;
- erros de rewrite podem confundir rotas da SPA e da API.

## Restrições

A decisão não autoriza:

- enviar rotas desconhecidas da API para o `index.html`;
- habilitar CORS irrestrito;
- colocar segredos no bundle do frontend;
- expor o diretório interno do backend;
- utilizar cookies sem `Secure` em produção;
- depender de URLs absolutas espalhadas no código;
- considerar mesma origem como substituta da proteção CSRF.

## Critérios de revisão futura

A decisão deverá ser reavaliada caso:

- frontend e backend sejam implantados em servidores diferentes;
- a API passe a atender outros clientes;
- seja criado aplicativo móvel nativo;
- a aplicação precise oferecer uma API pública;
- a infraestrutura deixe a hospedagem compartilhada;
- requisitos de escalabilidade exijam implantação independente;
- um gateway de API passe a ser utilizado.

## Relação com outros documentos

Este ADR complementa:

- `docs/architecture/shared-hosting-backend-plan.md`;
- `docs/architecture/adr/0001-arquitetura-backend-php.md`;
- `docs/migration/api-inventory.md`.

## Próximas decisões

Após a aceitação deste ADR, deverão ser definidos:

1. sessão e proteção CSRF;
2. modelo de dados de eventos e contatos;
3. cache meteorológico;
4. configuração e armazenamento de segredos;
5. regras definitivas de implantação no Apache.
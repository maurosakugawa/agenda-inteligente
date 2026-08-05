# Inventário da API da aplicação-fonte

## Identificação

- Repositório-fonte: `maurosakugawa/unified-platform`
- Commit analisado: `800e6bcf608496432bce413f34b4182f05e0206b`
- Backend original: Node.js, Express, express-session e PGlite/PostgreSQL
- Backend de destino: PHP 8.2 e MySQL/MariaDB
- Data da análise: 2026-08-05

## Objetivo

Registrar o contrato HTTP consumido pelo frontend antes da substituição
do backend Express/PGlite pelo backend PHP/MySQL.

O inventário descreve:

- métodos e caminhos;
- exigência de autenticação;
- parâmetros de entrada;
- respostas de sucesso;
- respostas de erro;
- comportamentos relevantes da aplicação-fonte;
- decisões necessárias para a implementação em PHP.

## Legenda de migração

- `pendente`: ainda não implementado em PHP;
- `implementado`: endpoint criado, mas ainda não validado integralmente;
- `validado`: contrato conferido com testes;
- `decisão necessária`: comportamento da fonte não deve ser copiado sem revisão.

---

# Convenções globais

## Formato

As respostas da API são JSON.

As respostas de erro possuem o formato:

```json
{
  "error": "Mensagem do erro"
}
```

## Corpo das requisições

O backend Express aceita JSON com limite de 1 MB.

## Autenticação

A autenticação é baseada em sessão.

Na aplicação-fonte, a sessão utiliza:

- cookie: `unified.sid`;
- `httpOnly`: habilitado;
- `sameSite`: `lax`;
- `secure`: configurável conforme o ambiente;
- duração: 24 horas;
- credenciais CORS: habilitadas quando frontend e backend usam origens diferentes.

Todas as rotas abaixo são protegidas:

- `/api/contacts`;
- `/api/events`;
- `/api/weather`.

Quando não existe uma sessão autenticada, a resposta é:

- status: `401`;
- corpo:

```json
{
  "error": "Não autenticado. Faça login."
}
```

## Isolamento por usuário

Contatos e eventos são consultados e alterados utilizando o identificador
do usuário armazenado na sessão.

A implementação em PHP deve impedir acesso a recursos pertencentes a
outro usuário, inclusive quando o cliente informar manualmente outro ID.

## Tratamento global de erros da fonte

O backend original aplica as seguintes regras:

1. mensagens contendo `não encontrado` recebem status `404`;
2. erros que possuem `status` utilizam esse status;
3. todos os demais erros recebem status `500`.

Esse comportamento gera respostas semanticamente incorretas em alguns
casos de autenticação. Essas inconsistências estão documentadas neste
inventário e não devem ser reproduzidas automaticamente.

## Rotas desconhecidas

### API

Caminhos desconhecidos sob `/api` retornam:

- status: `404`;

```json
{
  "error": "Rota de API não encontrada"
}
```

### Autenticação

Caminhos desconhecidos sob `/auth` retornam:

- status: `404`;

```json
{
  "error": "Rota de autenticação não encontrada"
}
```

### Demais caminhos

Quando a SPA não está sendo servida pelo Express, caminhos desconhecidos
retornam:

- status: `404`;

```json
{
  "error": "Rota não encontrada"
}
```

---

# Saúde da aplicação

## GET `/health`

### Autenticação

Não exige autenticação.

### Entrada

Não possui corpo nem parâmetros obrigatórios.

### Resposta de sucesso

Status: `200`.

```json
{
  "status": "ok",
  "phase": 5,
  "environment": "development",
  "uptime": 123
}
```

O valor de `environment` depende do ambiente e `uptime` representa o
tempo de execução do processo em segundos.

A resposta utiliza:

```http
Cache-Control: no-store
```

### Migração

Status: `pendente`.

O backend PHP deve substituir os campos específicos do processo Node por
informações equivalentes ou manter apenas os campos necessários para
monitoramento.

---

# Autenticação

## POST `/auth/register`

### Autenticação

Não exige autenticação.

### Corpo

```json
{
  "username": "usuario",
  "password": "senha"
}
```

### Validação da fonte

`username` e `password` são obrigatórios.

### Resposta de sucesso

Status: `201`.

```json
{
  "message": "Usuário criado",
  "userId": 1
}
```

### Erros da fonte

#### Campos ausentes

Status: `400`.

```json
{
  "error": "Username e senha são obrigatórios"
}
```

#### Usuário duplicado

Status atual da fonte: `500`.

```json
{
  "error": "Usuário já existe"
}
```

### Decisão para PHP

O usuário duplicado deve receber status `409`, mantendo uma resposta de
erro JSON compatível.

Status de migração: `pendente`.

---

## POST `/auth/login`

### Autenticação

Não exige autenticação.

### Corpo

```json
{
  "username": "usuario",
  "password": "senha"
}
```

### Validação da fonte

`username` e `password` são obrigatórios.

### Resposta de sucesso

Status: `200`.

```json
{
  "message": "Login realizado",
  "user": {
    "id": 1,
    "username": "usuario"
  }
}
```

Após a autenticação, a fonte grava na sessão:

- `userId`;
- `username`.

### Erros da fonte

#### Campos ausentes

Status: `400`.

```json
{
  "error": "Username e senha são obrigatórios"
}
```

#### Credenciais inválidas

Status atual da fonte: `500`.

```json
{
  "error": "Usuário ou senha inválidos"
}
```

### Decisão para PHP

Credenciais inválidas devem receber status `401`.

A mensagem não deve revelar se foi o usuário ou a senha que falhou.

Status de migração: `pendente`.

---

## POST `/auth/logout`

### Autenticação

A rota não possui o middleware global de autenticação, mas opera sobre a
sessão existente.

### Entrada

Não possui corpo obrigatório.

### Resposta de sucesso

Status: `200`.

```json
{
  "message": "Logout realizado"
}
```

### Comportamento

A sessão atual é destruída.

### Migração

Status: `pendente`.

---

## GET `/auth/me`

### Autenticação

Verifica diretamente a sessão.

### Entrada

Não possui corpo nem parâmetros.

### Resposta de sucesso

Status: `200`.

```json
{
  "id": 1,
  "username": "usuario"
}
```

### Erros

#### Sessão ausente

Status: `401`.

```json
{
  "error": "Não autenticado"
}
```

#### Usuário da sessão não existe mais

Status: `401`.

```json
{
  "error": "Usuário não encontrado"
}
```

Nesse caso, a sessão é destruída.

### Migração

Status: `pendente`.

---

# Contatos

## Estrutura de contato

A aplicação-fonte utiliza os campos:

```json
{
  "id": 1,
  "user_id": 1,
  "name": "Nome",
  "phone": "(11) 99999-9999",
  "email": "email@example.com",
  "cep": "00000-000",
  "logradouro": "Rua",
  "numero": "100",
  "bairro": "Centro",
  "cidade": "São Paulo",
  "uf": "SP",
  "created_at": "2026-08-05T12:00:00.000Z",
  "updated_at": "2026-08-05T12:00:00.000Z"
}
```

Na fonte, apenas `name` é obrigatório no banco. O serviço não possui uma
camada própria de validação dos demais campos.

A implementação PHP deve validar comprimentos, formatos e campos
obrigatórios antes de executar o SQL.

---

## GET `/api/contacts`

### Autenticação

Obrigatória.

### Entrada

Não possui parâmetros.

### Resposta de sucesso

Status: `200`.

Retorna um array de contatos pertencentes ao usuário autenticado.

A ordenação é por `created_at` decrescente.

### Migração

Status: `pendente`.

---

## POST `/api/contacts`

### Autenticação

Obrigatória.

### Corpo

```json
{
  "name": "Nome",
  "phone": "(11) 99999-9999",
  "email": "email@example.com",
  "cep": "00000-000",
  "logradouro": "Rua",
  "numero": "100",
  "bairro": "Centro",
  "cidade": "São Paulo",
  "uf": "SP"
}
```

### Resposta de sucesso

Status: `201`.

Retorna o contato completo criado.

### Migração

Status: `pendente`.

---

## PUT `/api/contacts/:id`

### Autenticação

Obrigatória.

### Parâmetro de caminho

- `id`: identificador do contato.

### Corpo

O corpo representa a atualização completa e utiliza os mesmos campos da criação.

### Resposta de sucesso

Status: `200`.

Retorna o contato completo atualizado.

### Erro

Quando o contato não existe ou não pertence ao usuário:

- status: `404`;

```json
{
  "error": "Contato não encontrado"
}
```

### Migração

Status: `pendente`.

---

## DELETE `/api/contacts/:id`

### Autenticação

Obrigatória.

### Parâmetro de caminho

- `id`: identificador do contato.

### Resposta de sucesso

Status: `200`.

```json
{
  "message": "Contato removido"
}
```

### Erro

Quando o contato não existe ou não pertence ao usuário:

- status: `404`;

```json
{
  "error": "Contato não encontrado"
}
```

### Migração

Status: `pendente`.

---

# Eventos

## Estrutura de evento

A aplicação-fonte utiliza os campos:

```json
{
  "id": 1,
  "user_id": 1,
  "title": "Título",
  "description": "Descrição",
  "event_date": "2026-08-10",
  "event_time": "14:00:00",
  "category": "geral",
  "priority": "media",
  "location": "São Paulo",
  "contact_ids": [1, 2],
  "reminder_minutes": 30,
  "created_at": "2026-08-05T12:00:00.000Z",
  "updated_at": "2026-08-05T12:00:00.000Z"
}
```

Na fonte, `contact_ids` é armazenado como texto JSON e convertido para
array antes de ser enviado ao frontend.

---

## GET `/api/events`

### Autenticação

Obrigatória.

### Parâmetros de consulta opcionais

- `from`: data inicial, comparada com `event_date`;
- `to`: data final, comparada com `event_date`;
- `category`: categoria exata.

Exemplo:

```http
GET /api/events?from=2026-08-01&to=2026-08-31&category=trabalho
```

### Resposta de sucesso

Status: `200`.

Retorna os eventos do usuário autenticado em um array.

A ordenação é:

1. `event_date` crescente;
2. `event_time` crescente.

### Migração

Status: `pendente`.

---

## POST `/api/events`

### Autenticação

Obrigatória.

### Corpo

```json
{
  "title": "Título",
  "description": "Descrição",
  "event_date": "2026-08-10",
  "event_time": "14:00",
  "category": "trabalho",
  "priority": "alta",
  "location": "São Paulo",
  "contact_ids": [1, 2],
  "reminder_minutes": 30
}
```

### Valores padrão da fonte

Quando omitidos:

- `category`: `geral`;
- `priority`: `media`;
- `contact_ids`: `[]`;
- `reminder_minutes`: `0`.

### Resposta de sucesso

Status: `201`.

Retorna o evento completo criado, com `contact_ids` convertido para
array.

### Migração

Status: `pendente`.

---

## PUT `/api/events/:id`

### Autenticação

Obrigatória.

### Parâmetro de caminho

- `id`: identificador do evento.

### Corpo

O corpo representa a atualização completa e utiliza os mesmos campos da criação.

### Resposta de sucesso

Status: `200`.

Retorna o evento completo atualizado, com `contact_ids` convertido para
array.

### Erro

Quando o evento não existe ou não pertence ao usuário:

- status: `404`;

```json
{
  "error": "Evento não encontrado"
}
```

### Migração

Status: `pendente`.

---

## DELETE `/api/events/:id`

### Autenticação

Obrigatória.

### Parâmetro de caminho

- `id`: identificador do evento.

### Resposta de sucesso

Status: `200`.

```json
{
  "message": "Evento removido"
}
```

### Erro

Quando o evento não existe ou não pertence ao usuário:

- status: `404`;

```json
{
  "error": "Evento não encontrado"
}
```

### Migração

Status: `pendente`.

---

## GET `/api/events/:id/contacts`

### Autenticação

Obrigatória.

### Parâmetro de caminho

- `id`: identificador do evento.

### Resposta de sucesso

Status: `200`.

Retorna um array com os contatos associados ao evento. Quando o evento
não possui contatos, retorna um array vazio.

### Erro

Quando o evento não existe ou não pertence ao usuário:

- status: `404`;

```json
{
  "error": "Evento não encontrado"
}
```

### Observação

Somente contatos pertencentes ao mesmo usuário são retornados.

### Migração

Status: `pendente`.

---

# Clima

## Regras globais

Os endpoints de clima são protegidos por autenticação.

A fonte consulta a OpenWeather exclusivamente no backend.

Configurações da fonte:

- cache por cidade: 30 minutos;
- máximo do cache: 200 entradas;
- timeout da chamada externa: 10 segundos;
- unidades: métricas;
- idioma: português;
- tamanho máximo da cidade: 120 caracteres.

A chave OpenWeather não pode ser enviada ao frontend nem incluída no
bundle de produção.

## Erros comuns

- cidade ausente: `400`, `Cidade é obrigatória`;
- cidade inválida: `400`, `Cidade inválida`;
- cidade não encontrada: `404`, `Cidade não encontrada`;
- chave não configurada: `503`, `OPENWEATHER_API_KEY não configurada no backend`;
- chave externa inválida: `503`, `Chave OpenWeather inválida ou não autorizada`;
- limite externo atingido: `503`, `Limite temporário da OpenWeather atingido`;
- falha do serviço externo: `502`, `Falha ao consultar a OpenWeather`;
- falha de conexão: `502`, `Não foi possível conectar à OpenWeather`;
- timeout: `504`, `Tempo limite ao consultar a OpenWeather`;
- resposta externa incompleta: `502`, `Resposta meteorológica incompleta`.

---

## GET `/api/weather/current`

### Autenticação

Obrigatória.

### Parâmetro de consulta

- `city`: obrigatório.

### Resposta de sucesso

Status: `200`.

```json
{
  "city": "São Paulo",
  "temp": 25,
  "feels_like": 26,
  "humidity": 70,
  "wind_speed": 3.5,
  "description": "céu limpo",
  "icon": "01d",
  "condition": "Clear",
  "isDay": true,
  "observedAt": 1785945600,
  "timezoneOffset": -10800,
  "observedTime": "14:00"
}
```

### Migração

Status: `pendente`.

---

## GET `/api/weather/forecast`

### Autenticação

Obrigatória.

### Parâmetro de consulta

- `city`: obrigatório.

### Resposta de sucesso

Status: `200`.

Retorna até cinco dias. Para cada dia, a fonte seleciona o intervalo de
três horas mais próximo de 12h no horário local da cidade.

### Migração

Status: `pendente`.

---

## GET `/api/weather/event`

### Autenticação

Obrigatória.

### Parâmetros de consulta

- `city`: obrigatório;
- `date`: obrigatório, no formato `YYYY-MM-DD`;
- `time`: opcional, preferencialmente no formato `HH:MM`.

### Erros de validação

- data inválida: `400`, `Data do evento inválida`;
- horário inválido: `400`, `Horário do evento inválido`.

### Resposta disponível

Status: `200`.

```json
{
  "status": "available",
  "city": "São Paulo",
  "date": "2026-08-10",
  "time": "15:00",
  "temp": 24,
  "description": "nublado",
  "icon": "04d",
  "condition": "Clouds",
  "selection": "nearest"
}
```

Possíveis valores de `selection`:

- `nearest`: intervalo mais próximo do horário informado;
- `next`: próximo intervalo disponível para evento no dia atual;
- `noon`: intervalo mais próximo de 12h quando não existe horário.

### Evento passado

Status: `200`.

```json
{
  "status": "unavailable",
  "reason": "past"
}
```

### Fora da janela de previsão

Status: `200`.

```json
{
  "status": "unavailable",
  "reason": "outside-window"
}
```

### Migração

Status: `pendente`.

---

# Decisões de segurança para o backend PHP

## CSRF

A aplicação-fonte não implementa uma proteção CSRF explícita.

O backend PHP deverá definir uma estratégia coordenada com o frontend
para as requisições mutáveis:

- `POST`;
- `PUT`;
- `PATCH`, caso seja introduzido;
- `DELETE`.

Status: `decisão necessária`.

## Sessão

A implementação PHP deverá:

- regenerar o identificador da sessão após o login;
- invalidar completamente a sessão no logout;
- usar cookie `HttpOnly`;
- usar `SameSite=Lax` ou política mais restritiva compatível;
- habilitar `Secure` em produção;
- configurar tempo de expiração;
- não armazenar senha nem hash de senha na sessão.

Status: `pendente`.

## Senhas

A fonte utiliza bcrypt com custo 10.

O PHP deverá usar:

```php
password_hash($password, PASSWORD_DEFAULT);
password_verify($password, $hash);
```

Status: `pendente`.

## Códigos HTTP de autenticação

A implementação PHP deverá corrigir:

| Situação | Fonte | Destino proposto |
|---|---:|---:|
| Campos obrigatórios ausentes | 400 | 400 |
| Credenciais inválidas | 500 | 401 |
| Usuário duplicado | 500 | 409 |
| Sessão ausente | 401 | 401 |
| Recurso não encontrado | 404 | 404 |

Status: `decisão necessária`.

---

# Ordem sugerida de implementação

1. infraestrutura HTTP e respostas JSON;
2. sessão e autenticação;
3. middleware de autenticação;
4. contatos;
5. eventos;
6. associação entre eventos e contatos;
7. proxy e cache de clima;
8. tratamento global de erros;
9. proteção CSRF;
10. testes de contrato.

---

# Matriz resumida

| Método | Caminho | Autenticação | Fonte | PHP |
|---|---|---:|---:|---:|
| GET | `/health` | Não | existente | pendente |
| POST | `/auth/register` | Não | existente | pendente |
| POST | `/auth/login` | Não | existente | pendente |
| POST | `/auth/logout` | Sessão opcional | existente | pendente |
| GET | `/auth/me` | Verificação própria | existente | pendente |
| GET | `/api/contacts` | Sim | existente | pendente |
| POST | `/api/contacts` | Sim | existente | pendente |
| PUT | `/api/contacts/:id` | Sim | existente | pendente |
| DELETE | `/api/contacts/:id` | Sim | existente | pendente |
| GET | `/api/events` | Sim | existente | pendente |
| POST | `/api/events` | Sim | existente | pendente |
| PUT | `/api/events/:id` | Sim | existente | pendente |
| DELETE | `/api/events/:id` | Sim | existente | pendente |
| GET | `/api/events/:id/contacts` | Sim | existente | pendente |
| GET | `/api/weather/current` | Sim | existente | pendente |
| GET | `/api/weather/forecast` | Sim | existente | pendente |
| GET | `/api/weather/event` | Sim | existente | pendente |

Total: 17 endpoints.

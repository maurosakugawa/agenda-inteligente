# ADR 0004 — Modelo de dados de eventos e contatos

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente possui como entidades centrais:

- usuários;
- contatos;
- eventos;
- associações entre eventos e contatos.

O frontend permite organizar compromissos e relacioná-los a uma ou mais pessoas.

Um contato pode participar de múltiplos eventos.

Um evento pode possuir múltiplos contatos.

Os registros devem permanecer isolados por usuário autenticado.

A arquitetura de destino utilizará:

- PHP 8.2;
- MySQL ou MariaDB;
- PDO;
- repositories;
- services;
- autenticação por sessão;
- propriedade dos registros derivada da sessão;
- sincronização futura com dados offline mantidos no frontend.

É necessário definir como eventos e contatos serão representados no banco de dados e como o relacionamento entre eles será persistido.

## Problema

As principais alternativas para representar os contatos associados a um evento são:

1. armazenar os contatos diretamente em uma coluna do evento;
2. armazenar somente um contato em cada evento;
3. duplicar dados do contato dentro do evento;
4. utilizar uma tabela associativa normalizada.

A decisão afeta:

- integridade referencial;
- consultas;
- filtros;
- atualizações;
- exclusões;
- sincronização;
- isolamento por usuário;
- manutenção;
- evolução do domínio.

## Requisitos do domínio

O modelo deverá permitir:

- um usuário possuir vários contatos;
- um usuário possuir vários eventos;
- um evento possuir zero, um ou vários contatos;
- um contato participar de zero, um ou vários eventos;
- associar e desassociar contatos sem duplicar eventos;
- excluir associações sem excluir necessariamente o contato;
- consultar contatos de um evento;
- consultar eventos de um contato;
- preservar isolamento entre usuários;
- executar alterações de associação de forma transacional;
- impedir associações duplicadas;
- impedir associação entre registros de usuários diferentes.

## Alternativa 1 — IDs em coluna JSON

Exemplo conceitual:

```json
{
  "contact_ids": [12, 18, 25]
}
```

### Vantagens

- estrutura inicial simples;
- leitura direta junto ao evento;
- menor quantidade inicial de tabelas.

### Desvantagens

- integridade referencial limitada;
- dificuldade para impedir IDs inexistentes;
- dificuldade para impedir contatos de outro usuário;
- consultas reversas mais complexas;
- índices menos eficientes;
- atualização concorrente mais delicada;
- duplicidade possível dentro do JSON;
- dependência de funções específicas do banco;
- manutenção mais difícil;
- relacionamento escondido dentro de uma coluna.

### Avaliação

Não recomendada.

## Alternativa 2 — Uma chave estrangeira no evento

Exemplo:

```text
events.contact_id
```

### Vantagens

- implementação simples;
- integridade referencial direta;
- consultas simples quando existe somente um contato.

### Desvantagens

- não representa vários contatos por evento;
- exigiria duplicar o evento para múltiplos participantes;
- não atende ao domínio conhecido;
- dificulta evolução futura.

### Avaliação

Não recomendada.

## Alternativa 3 — Dados do contato duplicados no evento

Exemplo:

```text
events.contact_name
events.contact_phone
events.contact_email
```

### Vantagens

- evento permanece independente após a criação;
- leitura sem junção;
- pode representar um snapshot histórico.

### Desvantagens

- duplicação de dados;
- atualizações inconsistentes;
- ausência de identidade única do contato;
- consultas reversas imprecisas;
- maior consumo de armazenamento;
- mistura o conceito de contato atual com snapshot histórico;
- não resolve adequadamente múltiplos contatos.

### Avaliação

Não recomendada como relacionamento principal.

Snapshots poderão ser considerados futuramente para requisitos históricos específicos, mas não substituirão a associação normalizada.

## Alternativa 4 — Tabela associativa normalizada

Estrutura conceitual:

```text
users
  |
  +-- contacts
  |
  +-- events
         |
         +-- event_contacts -- contacts
```

A tabela `event_contacts` conterá uma linha para cada associação entre evento e contato.

### Vantagens

- representa corretamente muitos-para-muitos;
- permite chave estrangeira;
- permite impedir duplicidade;
- facilita consultas nos dois sentidos;
- permite alterações transacionais;
- evita duplicação de contatos;
- funciona bem com MySQL e MariaDB;
- facilita índices;
- deixa o relacionamento explícito;
- permite evolução futura da associação.

### Desvantagens

- exige tabela adicional;
- consultas podem exigir `JOIN`;
- criação e atualização de eventos exigem coordenação transacional;
- isolamento por usuário precisa ser validado pelo service e pelas consultas.

### Avaliação

Recomendada.

## Decisão

Utilizar modelo relacional normalizado com:

- tabela `users`;
- tabela `contacts`;
- tabela `events`;
- tabela associativa `event_contacts`.

O relacionamento entre eventos e contatos será muitos-para-muitos.

A associação não será armazenada como JSON, lista separada por vírgulas ou dados duplicados dentro de `events`.

## Convenções gerais

As tabelas utilizarão:

- nomes em inglês;
- nomes no plural;
- `snake_case`;
- chaves primárias numéricas;
- `utf8mb4`;
- mecanismo InnoDB;
- timestamps técnicos `created_at` e `updated_at` em UTC;
- datas e horários civis dos eventos preservados separadamente;
- chaves estrangeiras;
- índices explícitos;
- prepared statements.

Os nomes definitivos poderão ser refinados nas migrations, mas o modelo lógico deverá ser preservado.

## Tabela `users`

Estrutura conceitual:

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A coluna `password_hash` armazenará o resultado de `password_hash`.

A senha original não será armazenada.

### Ciclo de vida do usuário

Usuários utilizarão exclusão lógica desde a primeira implementação.

A coluna `active` representa a disponibilidade da conta:

- `active = 1`: conta habilitada;
- `active = 0`: conta existente, porém desabilitada.

A coluna `deleted_at` representa exclusão lógica:

- `deleted_at IS NULL`: usuário não excluído;
- `deleted_at IS NOT NULL`: usuário excluído logicamente.

Usuários desabilitados ou excluídos logicamente não poderão autenticar.

Uma sessão autenticada deverá ser considerada inválida caso o usuário correspondente esteja com `active = 0` ou `deleted_at IS NOT NULL`.

O `username` permanecerá único mesmo após a exclusão lógica. Portanto, nomes de usuário excluídos não serão reutilizados automaticamente.

As operações normais da aplicação não deverão executar exclusão física de usuários.

## Tabela `contacts`

A estrutura inicial deverá preservar diretamente os campos do contrato HTTP atual.

Estrutura conceitual:

```sql
CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(254) NULL,
    cep VARCHAR(9) NULL,
    logradouro VARCHAR(190) NULL,
    numero VARCHAR(30) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_contacts_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT,

    KEY idx_contacts_user_name (user_id, name),
    KEY idx_contacts_user_email (user_id, email),
    KEY idx_contacts_user_phone (user_id, phone),
    KEY idx_contacts_user_cidade (user_id, cidade)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

Os nomes dos campos preservam o contrato atual:

```text
name
phone
email
cep
logradouro
numero
bairro
cidade
uf
```

A primeira migration não deverá substituir esses nomes silenciosamente por:

```text
postal_code
street
number
district
city
state
```

Uma tradução entre nomes internos e nomes da API somente poderá ser adotada caso seja intencional, documentada e testada.

Campos adicionais como empresa, complemento, aniversário e observações poderão ser acrescentados futuramente, mas não fazem parte do contrato atual e não deverão ser introduzidos como requisito desta migração.

## Tabela `events`

A estrutura inicial deverá preservar o contrato atual de eventos.

Estrutura conceitual:

```sql
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'geral',
    priority VARCHAR(20) NOT NULL DEFAULT 'media',
    location VARCHAR(255) NULL,
    reminder_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT,

    KEY idx_events_user_date_time (
        user_id,
        event_date,
        event_time
    ),
    KEY idx_events_user_category (
        user_id,
        category
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

Os campos preservados pelo contrato são:

```text
title
description
event_date
event_time
category
priority
location
reminder_minutes
```

`contact_ids` não será uma coluna de `events`.

As associações serão persistidas na tabela `event_contacts` e convertidas novamente para `contact_ids` nas respostas da API.

A primeira implementação não utilizará:

```text
starts_at
ends_at
all_day
status
```

Esses campos representam uma evolução possível do domínio, mas não fazem parte do contrato atual e exigiriam decisões próprias sobre fuso horário, duração, eventos de dia inteiro e estados do evento.

## Tabela `event_contacts`

Estrutura conceitual:

```sql
CREATE TABLE event_contacts (
    event_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (event_id, contact_id),

    CONSTRAINT fk_event_contacts_event
        FOREIGN KEY (event_id)
        REFERENCES events (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_event_contacts_contact
        FOREIGN KEY (contact_id)
        REFERENCES contacts (id)
        ON DELETE CASCADE,

    KEY idx_event_contacts_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A chave primária composta impedirá que o mesmo contato seja associado duas vezes ao mesmo evento.

## Ausência inicial de `user_id` em `event_contacts`

A tabela associativa não terá inicialmente uma coluna `user_id`.

A propriedade será derivada por meio de:

- `events.user_id`;
- `contacts.user_id`.

Uma associação somente poderá ser criada quando:

```text
events.user_id = contacts.user_id = usuário autenticado
```

Adicionar `user_id` à tabela associativa duplicaria uma informação que já existe nas duas entidades relacionadas e criaria risco de inconsistência.

Essa decisão poderá ser revista caso exista necessidade comprovada de particionamento, auditoria ou otimização.

## Isolamento por usuário

Toda consulta de contatos e eventos deverá filtrar pelo usuário autenticado.

Exemplo conceitual:

```sql
SELECT
    id,
    name,
    email,
    phone,
    created_at,
    updated_at
FROM contacts
WHERE id = :contact_id
  AND user_id = :user_id
LIMIT 1;
```

Não será suficiente consultar somente pelo ID:

```sql
WHERE id = :contact_id
```

O mesmo princípio será aplicado a eventos.

## Validação da associação

Antes de associar um contato a um evento, o service deverá confirmar que:

1. o evento existe;
2. o evento pertence ao usuário autenticado;
3. o contato existe;
4. o contato pertence ao mesmo usuário;
5. a associação ainda não existe ou pode ser tratada de forma idempotente.

O frontend não será considerado fonte confiável para propriedade.

## Criação de evento

Quando a criação incluir contatos, a operação deverá ocorrer em transação:

```text
BEGIN

1. inserir evento;
2. validar contatos informados;
3. inserir associações em event_contacts;
4. confirmar transação.

COMMIT
```

Caso qualquer etapa falhe:

```text
ROLLBACK
```

Não deverá permanecer evento parcialmente associado por falha intermediária.

## Atualização de evento

O corpo de `PUT /api/events/:id` representa uma atualização completa.

Quando o corpo contém `contact_ids`, essa lista representa o conjunto completo desejado de contatos associados ao evento.

A atualização deverá:

1. iniciar uma transação;
2. validar que o evento pertence ao usuário autenticado;
3. normalizar os IDs repetidos;
4. validar todos os contatos;
5. confirmar que todos pertencem ao mesmo usuário;
6. atualizar os campos do evento;
7. substituir transacionalmente as associações;
8. confirmar a transação.

A implementação poderá calcular internamente as diferenças entre:

- associações existentes;
- associações desejadas;
- associações adicionadas;
- associações removidas.

A semântica externa continuará sendo de substituição completa da lista.

Caso qualquer contato seja inválido, nenhuma parte da atualização deverá permanecer salva.

## Exclusão de evento

A exclusão física de um evento removerá automaticamente suas associações devido a:

```text
ON DELETE CASCADE
```

Os contatos não serão excluídos.

## Exclusão de contato

A exclusão física de um contato removerá automaticamente suas associações em `event_contacts`.

Os eventos relacionados não serão excluídos.

Antes da exclusão, a API poderá informar quantos eventos estão relacionados, caso isso faça parte da experiência definida no frontend.

## Política de exclusão

A política de exclusão depende da entidade.

### Usuários

Usuários utilizarão exclusão lógica desde a primeira implementação.

A exclusão será representada por `users.deleted_at`.

A desativação temporária ou administrativa será representada separadamente por `users.active`.

Contatos, eventos e demais dados pertencentes ao usuário não deverão ser apagados em consequência da exclusão lógica ou desativação da conta.

As chaves estrangeiras de `contacts` e `events` para `users` deverão impedir exclusão física enquanto existirem registros dependentes.

### Contatos e eventos

A primeira implementação poderá continuar utilizando exclusão física para contatos e eventos, preservando o contrato atual.

Exclusão lógica para contatos ou eventos somente será adicionada se houver requisito claro de:

- recuperação;
- auditoria;
- histórico;
- sincronização com tombstones;
- retenção.

Não serão adicionadas colunas `deleted_at` a contatos ou eventos sem definição do comportamento da API e da sincronização.

## Ordenação dos contatos de um evento

A tabela associativa não armazenará inicialmente uma posição manual.

A API poderá retornar os contatos ordenados por:

```text
contacts.name
```

Caso o frontend necessite ordenação definida pelo usuário, poderá ser adicionada futuramente uma coluna:

```text
position
```

Essa coluna não será criada antecipadamente sem requisito funcional.

## Metadados da associação

A associação inicialmente representará apenas participação ou relacionamento.

Não serão adicionados sem necessidade:

- papel do contato;
- confirmação;
- presença;
- observações específicas;
- prioridade;
- tipo de participação;
- status de convite.

Caso o domínio evolua, essas informações poderão ser adicionadas à tabela `event_contacts`, que já representa a associação como entidade explícita.

## Consultar contatos de um evento

Exemplo conceitual:

```sql
SELECT
    c.id,
    c.name,
    c.email,
    c.phone
FROM event_contacts ec
INNER JOIN contacts c
    ON c.id = ec.contact_id
INNER JOIN events e
    ON e.id = ec.event_id
WHERE ec.event_id = :event_id
  AND e.user_id = :user_id
  AND c.user_id = :user_id
ORDER BY c.name, c.id;
```

O filtro das duas entidades funciona como defesa adicional contra dados inconsistentes.

## Consultar eventos de um contato

Exemplo conceitual:

```sql
SELECT
    e.id,
    e.title,
    e.event_date,
    e.event_time,
    e.location
FROM event_contacts ec
INNER JOIN events e
    ON e.id = ec.event_id
INNER JOIN contacts c
    ON c.id = ec.contact_id
WHERE ec.contact_id = :contact_id
  AND e.user_id = :user_id
  AND c.user_id = :user_id
ORDER BY
    e.event_date,
    e.event_time,
    e.id;
```

## Resposta da API

As respostas de criação, atualização e listagem de eventos deverão preservar o contrato atual.

Exemplo:

```json
{
  "id": 42,
  "user_id": 7,
  "title": "Reunião",
  "description": "Revisão do projeto",
  "event_date": "2026-08-10",
  "event_time": "14:00:00",
  "category": "trabalho",
  "priority": "alta",
  "location": "São Paulo",
  "contact_ids": [12, 18],
  "reminder_minutes": 30,
  "created_at": "2026-08-06T12:00:00Z",
  "updated_at": "2026-08-06T12:00:00Z"
}
```

O campo `contact_ids` será construído pelo backend a partir de `event_contacts`.

Ele não será obtido de uma coluna JSON em `events`.

O endpoint:

```text
GET /api/events/:id/contacts
```

continuará retornando os registros completos dos contatos associados ao evento.

## Entrada da API

A criação e a atualização continuarão aceitando o contrato atual:

```json
{
  "title": "Reunião",
  "description": "Revisão do projeto",
  "event_date": "2026-08-10",
  "event_time": "14:00",
  "category": "trabalho",
  "priority": "alta",
  "location": "São Paulo",
  "contact_ids": [12, 18],
  "reminder_minutes": 30
}
```

A presença de `contact_ids` no JSON da API não significa armazenamento em JSON no banco.

O controller deverá:

- validar que `contact_ids` é uma lista;
- aceitar somente identificadores inteiros positivos;
- normalizar duplicidades;
- limitar a quantidade máxima permitida;
- validar `event_date`;
- validar `event_time` quando informado;
- encaminhar a operação ao service.

O service será responsável pela validação de propriedade e pela transação.

## Lista vazia de contatos

Um evento poderá não possuir contatos.

A entrada:

```json
{
  "contact_ids": []
}
```

será válida quando o contrato permitir evento sem contato.

No banco, isso significa ausência de linhas correspondentes em `event_contacts`.

Não será criado registro com `contact_id` nulo.

## Duplicidade na entrada

A entrada:

```json
{
  "contact_ids": [12, 12, 18]
}
```

será normalizada para:

```json
{
  "contact_ids": [12, 18]
}
```

A duplicidade não deverá causar associações repetidas nem erro para o usuário.

A chave primária composta de `event_contacts` continuará funcionando como proteção final de integridade.

A ordem de `contact_ids` não representará uma ordenação persistente, pois a tabela associativa inicial não possuirá coluna de posição.

## Contato inexistente

Quando um ID inválido for enviado dentro de `contact_ids` durante criação ou atualização de evento, a operação inteira deverá falhar com:

```text
422 Unprocessable Entity
```

Exemplo:

```json
{
  "error": "Um ou mais contatos são inválidos"
}
```

O mesmo comportamento será utilizado quando o contato existir, mas pertencer a outro usuário.

A resposta não deverá revelar que um recurso pertence a outro usuário.

Para acesso direto a um contato ou evento por seu endpoint próprio, um recurso inexistente ou pertencente a outro usuário continuará retornando:

```text
404 Not Found
```

Isso preserva a convenção atual da API sem permitir enumeração de recursos.

## Datas e horários

Os campos do compromisso serão preservados separadamente:

```text
event_date DATE
event_time TIME NULL
```

Eles representam a data e o horário civil informados pelo usuário.

Exemplo:

```text
event_date = 2026-08-10
event_time = 14:00:00
```

Esses valores não deverão ser convertidos automaticamente para UTC durante a migração.

Na primeira versão da aplicação, `event_date` e `event_time` serão interpretados no fuso horário civil:

```text
America/Sao_Paulo
```

Esse é o fuso adotado pela Agenda Inteligente para representar o horário de Brasília na operação da aplicação.

Assim, um compromisso informado como:

```text
event_date = 2026-08-10
event_time = 14:00:00
```

continuará sendo apresentado e tratado como 14:00 no horário civil de `America/Sao_Paulo`.

A configuração operacional da aplicação deverá permanecer coerente com essa decisão por meio de:

```text
app.timezone = America/Sao_Paulo
```

A aplicação ainda não possui, no contrato atual, um campo de fuso horário associado individualmente ao evento ou ao usuário.

A introdução futura de fusos por usuário ou por evento exigirá decisão arquitetural própria e não deverá alterar silenciosamente o significado dos registros já persistidos.

Combinar `event_date` e `event_time` em `starts_at` e convertê-los automaticamente para UTC poderia:

- alterar o dia do evento;
- alterar a hora exibida;
- quebrar filtros por data;
- afetar lembretes;
- afetar consultas meteorológicas;
- produzir resultados diferentes entre ambientes.

Essa regra de horário civil não se aplica aos timestamps técnicos.

Os timestamps técnicos definidos neste ADR:

```text
created_at
updated_at
deleted_at
```

quando existentes na respectiva entidade, serão armazenados e tratados em UTC.

As sessões de banco utilizadas pela aplicação deverão operar em UTC para que valores produzidos pelo próprio MySQL ou MariaDB, como `CURRENT_TIMESTAMP`, sigam a mesma referência temporal.

Quando um timestamp técnico precisar ser apresentado ao usuário, a aplicação deverá convertê-lo de UTC para `America/Sao_Paulo`.

A API continuará enviando `event_date` e `event_time` no formato esperado pelo frontend, preservando seu significado de data e horário civil.

## Eventos sem horário

O contrato atual permite que `event_time` seja nulo ou vazio.

Nessa situação, o evento possui data, mas não possui horário definido.

A primeira implementação não criará um campo `all_day`.

Também não criará `ends_at`, pois o contrato atual não representa duração ou horário final.

Caso sejam necessários eventos de dia inteiro ou intervalos com início e fim, essa evolução deverá definir:

- semântica de `all_day`;
- comportamento de `event_time`;
- horário final;
- fuso horário;
- conversão para UTC;
- compatibilidade com eventos existentes;
- impacto sobre clima e lembretes.

## Índices

Índices mínimos previstos:

```text
contacts:
- PRIMARY KEY (id)
- INDEX (user_id, name)
- INDEX (user_id, email)
- INDEX (user_id, phone)
- INDEX (user_id, cidade)

events:
- PRIMARY KEY (id)
- INDEX (user_id, event_date, event_time)
- INDEX (user_id, category)

event_contacts:
- PRIMARY KEY (event_id, contact_id)
- INDEX (contact_id)
```

Esses índices correspondem às consultas atuais:

- contatos por usuário;
- busca e ordenação por nome;
- eventos por intervalo de datas;
- filtro de eventos por categoria;
- contatos associados a um evento;
- eventos associados a um contato.

Índices adicionais somente deverão ser introduzidos com base em consultas reais.

## Unicidade de contatos

Não será imposta inicialmente unicidade global de:

- nome;
- telefone;
- e-mail.

Pessoas diferentes podem compartilhar nomes, telefones ou endereços de e-mail.

Caso o domínio exija prevenção de duplicidade, a regra deverá ser definida por usuário e não globalmente.

## Integridade referencial

As migrations deverão criar as tabelas na seguinte ordem:

1. `users`;
2. `contacts`;
3. `events`;
4. `event_contacts`.

A remoção deverá ocorrer na ordem inversa.

As chaves estrangeiras deverão utilizar tipos idênticos entre coluna de origem e destino.

## Transações

O service será responsável por delimitar transações de negócio.

Repositories não deverão confirmar isoladamente uma operação composta.

Exemplo conceitual:

```php
$pdo->beginTransaction();

try {
    $eventId = $eventRepository->create($userId, $eventData);

    $eventContactRepository->replaceContacts(
        $eventId,
        $contactIds
    );

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
```

O código definitivo deverá validar os contatos antes ou durante a transação sem deixar estado parcial.

## Responsabilidade dos repositories

### `ContactRepository`

Responsável por:

- criar contato;
- consultar por ID e usuário;
- listar por usuário;
- atualizar por ID e usuário;
- excluir por ID e usuário;
- verificar existência e propriedade de IDs.

### `EventRepository`

Responsável por:

- criar evento;
- consultar por ID e usuário;
- listar por usuário e período;
- atualizar por ID e usuário;
- excluir por ID e usuário.

### `EventContactRepository`

Responsável por:

- listar contatos de um evento;
- listar eventos de um contato;
- adicionar associações;
- remover associações;
- substituir associações;
- verificar duplicidade quando necessário.

Os repositories não deverão decidir autorização apenas com base em IDs recebidos.

## Responsabilidade dos services

O service deverá:

- obter o usuário autenticado do contexto;
- validar propriedade;
- validar regras de negócio;
- coordenar repositories;
- controlar transações;
- impedir associações entre usuários;
- transformar conflitos de domínio em erros apropriados.

## Migração dos dados existentes

A fonte atual armazena `contact_ids` como texto JSON dentro de cada evento.

A migração deverá:

1. identificar os usuários;
2. migrar os contatos preservando os campos atuais;
3. migrar os eventos sem a coluna JSON `contact_ids`;
4. interpretar o JSON de cada evento;
5. normalizar IDs repetidos;
6. validar a existência dos contatos;
7. validar que evento e contato pertencem ao mesmo usuário;
8. criar uma linha em `event_contacts` para cada associação válida;
9. registrar associações inválidas;
10. comparar quantidades antes e depois;
11. manter possibilidade de rollback.

Não serão inventadas associações inexistentes.

A migration existente:

```text
database/migrations/001_initial_schema.sql
```

deverá ser tratada como provisória e alinhada a este ADR antes da implantação.

No estado atual, ela contém decisões incompatíveis com o contrato preservado:

- nomes ingleses diferentes para campos de endereço;
- campos adicionais de contatos;
- `starts_at`, `ends_at` e `all_day`;
- `event_participants` em vez de `event_contacts`;
- `response_status`;
- exclusão lógica genérica por `deleted_at` em entidades cujo contrato ainda não define essa política;
- estados de evento ainda não definidos.

Esses elementos não deverão chegar à produção sem ADR e adaptação explícita do contrato.

## Validação da migração

A validação deverá comparar pelo menos:

- quantidade de usuários;
- quantidade de contatos;
- quantidade de eventos;
- quantidade de associações;
- eventos sem contatos;
- contatos sem eventos;
- associações duplicadas;
- referências inexistentes;
- associações entre usuários diferentes.

Qualquer discrepância deverá ser registrada.

## Sincronização offline

A normalização no servidor não impede que o frontend use estruturas adequadas ao IndexedDB.

O frontend poderá manter:

- eventos;
- contatos;
- IDs associados;
- fila de operações.

No envio para a API, as associações deverão ser representadas por IDs conforme o contrato.

A sincronização não deverá enviar `user_id` como fonte de propriedade.

## Identificadores temporários

O frontend offline poderá precisar criar IDs temporários.

Esses IDs não serão persistidos diretamente nas chaves numéricas do servidor.

A estratégia de mapeamento entre IDs locais e IDs do servidor será definida no plano de sincronização.

O presente ADR não define o algoritmo completo de sincronização.

## Testes obrigatórios

A implementação deverá possuir testes para:

- criação de contato;
- criação de evento sem contatos;
- criação de evento com um contato;
- criação de evento com vários contatos;
- associação duplicada;
- contato inexistente;
- contato de outro usuário;
- evento de outro usuário;
- atualização das associações;
- remoção de uma associação;
- exclusão de evento;
- exclusão de contato;
- consulta de contatos do evento;
- consulta de eventos do contato;
- rollback em falha parcial;
- isolamento entre usuários;
- lista vazia;
- preservação de `event_date` e `event_time`;
- timestamps técnicos em UTC;
- preservação do contrato da API.

## Consequências positivas

- modelo relacional normalizado;
- integridade referencial;
- relacionamento muitos-para-muitos explícito;
- prevenção de duplicidade;
- consultas bidirecionais;
- ausência de contatos duplicados dentro dos eventos;
- evolução futura da associação;
- transações consistentes;
- compatibilidade com MySQL e MariaDB;
- melhor isolamento por usuário;
- migrations previsíveis.

## Consequências negativas

- tabela adicional;
- necessidade de `JOIN`;
- operações compostas precisam de transação;
- repositories e services precisam coordenar propriedade;
- sincronização offline precisa mapear associações;
- exclusão e atualização exigem testes adicionais;
- formato da API precisa ser alinhado com o frontend.

## Restrições

A decisão não autoriza:

- armazenar IDs separados por vírgula;
- utilizar coluna JSON como relacionamento principal;
- duplicar dados completos de contatos nos eventos;
- confiar em `user_id` enviado pelo frontend;
- associar contato e evento de usuários diferentes;
- criar associação sem validar propriedade;
- realizar criação parcial quando uma associação falhar;
- remover contato ao apenas desassociá-lo de um evento;
- remover evento ao excluir um contato;
- introduzir exclusão lógica sem definir sincronização e contrato;
- alterar silenciosamente o formato da API.

## Critérios de revisão futura

A decisão deverá ser reavaliada caso:

- a associação passe a possuir muitos atributos próprios;
- seja necessário preservar snapshots históricos;
- eventos passem a ter convidados externos sem cadastro;
- seja definido um modelo de fuso horário por usuário ou evento;
- sejam introduzidos eventos com início, fim ou dia inteiro;
- seja adotado UUID como identificador principal;
- o volume de associações exija particionamento;
- a aplicação passe a utilizar múltiplos bancos;
- a estratégia de sincronização exija tombstones;
- novos requisitos de auditoria sejam introduzidos.

## Relação com outros documentos

Este ADR complementa:

- `docs/architecture/shared-hosting-backend-plan.md`;
- `docs/architecture/adr/0001-arquitetura-backend-php.md`;
- `docs/architecture/adr/0002-topologia-producao-mesma-origem.md`;
- `docs/architecture/adr/0003-sessao-php-e-protecao-csrf.md`;
- `docs/migration/api-inventory.md`;
- `docs/migration/source-baseline.md`.

## Próximas decisões

Após a aceitação deste ADR, deverão ser definidos:

1. cache meteorológico;
2. configuração e armazenamento de segredos;
3. estratégia de sincronização;
4. migrations e implantação do banco;
5. regras definitivas de publicação no Apache.

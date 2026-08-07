# ADR 0003 — Sessão PHP e proteção CSRF

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente terá:

- frontend React, TypeScript e Vite;
- backend modular em PHP 8.2;
- banco MySQL ou MariaDB;
- autenticação baseada em usuário e senha;
- implantação em hospedagem compartilhada;
- frontend e backend servidos sob a mesma origem;
- endpoints HTTP que manipulam dados privados de cada usuário.

Os ADRs anteriores estabeleceram:

- backend PHP modular com Composer e PSR-4;
- front controller, middleware, controllers, services e repositories;
- frontend e API sob a mesma origem;
- uso de caminhos relativos pelo frontend;
- ausência de CORS no fluxo normal de produção.

O backend original utiliza `express-session`.

Na arquitetura de destino, é necessário definir:

- como a sessão autenticada será armazenada;
- como o cookie será configurado;
- como será evitada fixação de sessão;
- como ocorrerá login e logout;
- quais dados poderão permanecer na sessão;
- como será aplicado o tempo de expiração;
- como requisições mutáveis serão protegidas contra CSRF.

## Problema

Cookies de sessão são enviados automaticamente pelo navegador.

Esse comportamento é necessário para autenticação, mas também permite que um site malicioso tente induzir o navegador do usuário a enviar requisições autenticadas para a aplicação.

Portanto, a arquitetura precisa proteger simultaneamente contra:

- roubo de sessão;
- fixação de sessão;
- reutilização de sessão expirada;
- acesso indevido ao cookie pelo JavaScript;
- CSRF;
- autenticação inconsistente entre endpoints;
- vazamento de dados entre usuários;
- manutenção de sessões após logout.

## Critérios de decisão

A solução deverá priorizar:

- compatibilidade com hospedagem compartilhada;
- uso de recursos nativos e maduros do PHP;
- armazenamento do identificador de sessão em cookie seguro;
- armazenamento dos dados da sessão no servidor;
- proteção contra fixação de sessão;
- proteção CSRF para métodos mutáveis;
- integração simples com o frontend React;
- mesma origem;
- ausência de tokens de autenticação no `localStorage`;
- tratamento centralizado por middleware;
- expiração previsível;
- possibilidade de revogação;
- facilidade de testes.

## Alternativas de autenticação consideradas

### Alternativa 1 — Sessão nativa do PHP

O navegador recebe somente um identificador opaco de sessão.

Os dados associados permanecem no servidor.

#### Vantagens

- integração nativa com PHP;
- compatibilidade com hospedagem compartilhada;
- cookie pequeno;
- dados sensíveis não ficam no navegador;
- revogação imediata no servidor;
- implementação adequada à mesma origem;
- menor complexidade que uma infraestrutura JWT;
- suporte direto à regeneração do identificador;
- integração simples com middleware.

#### Desvantagens

- exige armazenamento de sessão no servidor;
- exige configuração correta do mecanismo de expiração;
- exige atenção ao diretório de armazenamento;
- uma futura arquitetura distribuída exigiria armazenamento compartilhado;
- sessões antigas precisam ser coletadas adequadamente.

#### Avaliação

Recomendada.

### Alternativa 2 — JWT armazenado no navegador

O token conteria informações assinadas e seria enviado pelo frontend em cada requisição.

#### Vantagens

- não exige armazenamento tradicional de sessão;
- pode facilitar APIs consumidas por múltiplos clientes;
- pode ser adequado a arquiteturas distribuídas.

#### Desvantagens

- revogação mais complexa;
- risco elevado quando armazenado no `localStorage`;
- renovação e rotação mais complexas;
- expiração não substitui revogação;
- adiciona complexidade desnecessária ao escopo atual;
- não oferece vantagem proporcional para frontend e backend na mesma origem;
- exige decisões adicionais sobre refresh tokens;
- aumenta o risco de implementação incorreta.

#### Avaliação

Não recomendada.

### Alternativa 3 — Token opaco enviado em cabeçalho

O frontend armazenaria um token e o enviaria em `Authorization`.

#### Vantagens

- explícito nas requisições;
- pode ser utilizado por clientes diferentes de navegadores;
- permite armazenamento central no servidor.

#### Desvantagens

- exige armazenamento seguro no frontend;
- pode acabar persistido em `localStorage`;
- acrescenta gerenciamento de token sem necessidade;
- não aproveita diretamente a sessão nativa do PHP;
- não elimina a necessidade de revogação e expiração;
- é desproporcional à mesma origem.

#### Avaliação

Não recomendada para a aplicação web atual.

## Decisão

Utilizar sessão nativa do PHP para autenticação.

A sessão será identificada por cookie seguro.

Os dados da sessão permanecerão no servidor.

A proteção CSRF utilizará o padrão de token sincronizador:

- o token será gerado pelo backend;
- o token será armazenado na sessão;
- o frontend receberá o token;
- métodos mutáveis deverão enviá-lo no cabeçalho `X-CSRF-Token`;
- o backend comparará o token recebido com o token da sessão usando comparação segura.

## Nome do cookie

O cookie terá nome específico da aplicação.

Nome inicial proposto:

```text
agenda_inteligente_sid
```

Não deverá ser utilizado o nome genérico padrão `PHPSESSID` em produção.

O nome poderá ser alterado por configuração, mas deverá permanecer:

- específico da aplicação;
- estável durante uma implantação;
- não relacionado ao nome do usuário;
- sem informações sensíveis.

## Configuração da sessão PHP

A configuração deverá aplicar conceitualmente:

```ini
session.use_only_cookies = 1
session.use_strict_mode = 1
session.use_trans_sid = 0
session.cookie_httponly = 1
session.cookie_samesite = Lax
```

Em produção:

```ini
session.cookie_secure = 1
```

O backend deverá configurar esses valores antes de iniciar a sessão quando a hospedagem permitir configuração em tempo de execução.

A aplicação não deverá depender exclusivamente do `php.ini` global da hospedagem.

## Configuração do cookie

O cookie de sessão deverá possuir:

- `HttpOnly`;
- `Secure` em produção;
- `SameSite=Lax`;
- `Path=/`;
- nome específico da aplicação;
- ausência de dados pessoais;
- ausência de informações de autorização;
- ausência de token CSRF em texto acessível ao JavaScript.

### HttpOnly

Impede que o JavaScript leia diretamente o cookie de sessão.

Essa proteção reduz o impacto de determinadas formas de XSS, mas não substitui prevenção contra XSS.

### Secure

Em produção, o cookie somente deverá ser enviado por HTTPS.

A aplicação de produção não deverá permitir autenticação por HTTP sem TLS.

### SameSite

A política inicial será:

```text
SameSite=Lax
```

Essa política oferece compatibilidade com navegação normal e reduz parte das requisições entre sites.

`SameSite` será considerado defesa adicional e não substituto do token CSRF.

### Path

O caminho inicial será:

```text
/
```

Isso permite uso do mesmo cookie em:

- `/auth/*`;
- `/api/*`;
- demais rotas autenticadas futuras.

O endpoint público `/health` não deverá iniciar nem exigir sessão, ainda que o navegador possa enviar o cookie devido ao caminho `/`.

## Armazenamento da sessão

A primeira implementação utilizará o mecanismo nativo de arquivos de sessão do PHP.

Essa escolha é adequada porque:

- a implantação inicial utilizará um único ambiente de hospedagem;
- não haverá múltiplos servidores PHP;
- não existe necessidade inicial de Redis;
- o volume esperado é compatível;
- o PHP já possui suporte nativo.

O diretório de sessões deverá ser:

- privado;
- não acessível pela web;
- gravável pelo processo PHP;
- separado do diretório público;
- compatível com as regras da hospedagem.

A aplicação não deverá salvar arquivos de sessão dentro de `public_html` ou de qualquer diretório servido pelo Apache.

### Retenção física e garbage collection

Os limites de expiração definidos pela aplicação não deverão ser antecipados pela coleta automática de lixo do PHP.

Quando o handler nativo de arquivos for utilizado, a configuração deverá garantir que:

```text
session.gc_maxlifetime >= session.idle_timeout
```

Na configuração inicial proposta, isso significa que o armazenamento físico deverá ser capaz de reter uma sessão inativa por pelo menos 30 minutos.

Sempre que a hospedagem permitir configuração em tempo de execução, o backend deverá ajustar `session.gc_maxlifetime` antes de iniciar a sessão.

O diretório de sessões deverá preferencialmente ser exclusivo da aplicação, evitando que outras aplicações com políticas diferentes de garbage collection interfiram nesses arquivos.

Caso a hospedagem compartilhada não permita controle confiável do garbage collector global, deverá ser utilizado um `session.save_path` isolado ou outro mecanismo de armazenamento que ofereça retenção equivalente.

A existência física do arquivo não determinará a validade da sessão.

Os timestamps mantidos pela aplicação continuarão sendo a autoridade para:

- expiração por inatividade;
- expiração absoluta;
- rejeição de sessões expiradas.

Assim, o garbage collector será responsável somente pela liberação posterior do armazenamento e não pela regra de autenticação.

## Dados permitidos na sessão

A sessão deverá conter somente dados necessários à autenticação e segurança.

Estrutura conceitual:

```php
$_SESSION = [
    'auth' => [
        'user_id' => 123,
        'username' => 'usuario',
        'authenticated_at' => 1785985200,
    ],
    'security' => [
        'csrf_token' => 'token-aleatorio',
        'created_at' => 1785985200,
        'last_activity_at' => 1785985200,
        'absolute_expires_at' => 1786071600,
    ],
];
```

A estrutura definitiva poderá utilizar classes ou serviços, mas deverá preservar essas responsabilidades.

Não deverão ser armazenados na sessão:

- senha;
- hash de senha;
- chave da OpenWeather;
- dados completos de contatos;
- dados completos de eventos;
- respostas meteorológicas;
- informações desnecessárias do perfil;
- permissões sem validação no servidor;
- mensagens técnicas de exceções.

## Identidade autenticada

O identificador principal da sessão será o ID interno do usuário.

Exemplo:

```text
user_id = 123
```

O backend deverá utilizar esse ID para aplicar isolamento em consultas.

Dados enviados pelo frontend nunca deverão substituir o usuário autenticado da sessão.

Exemplo proibido:

```json
{
  "user_id": 999,
  "title": "Evento"
}
```

Mesmo que `user_id` seja enviado, o backend deverá ignorá-lo ou rejeitá-lo.

O proprietário dos registros será sempre derivado da sessão autenticada.

## Criação da sessão

Uma sessão poderá ser iniciada antes da autenticação para fornecer token CSRF.

A sessão anônima deverá conter apenas dados de segurança necessários.

Após autenticação bem-sucedida:

1. validar usuário e senha;
2. regenerar o identificador da sessão;
3. remover dados anônimos que não sejam mais necessários;
4. registrar o usuário autenticado;
5. gerar novo token CSRF;
6. definir os tempos de expiração;
7. enviar a resposta de sucesso.

## Regeneração do identificador

Após login bem-sucedido, deverá ser executada regeneração do identificador:

```php
session_regenerate_id(true);
```

A regeneração deverá ocorrer antes de considerar a sessão autenticada concluída.

O identificador anterior deverá ser invalidado.

Também poderá ocorrer regeneração periódica durante sessões longas, desde que não quebre requisições concorrentes.

## Login

O endpoint continuará sendo:

```text
POST /auth/login
```

O login deverá:

- aceitar somente JSON válido;
- validar tamanho e formato do usuário;
- validar tamanho da senha;
- localizar o usuário pelo identificador definido;
- utilizar `password_verify`;
- retornar `401` para credenciais inválidas;
- evitar informar qual campo estava incorreto;
- regenerar o identificador da sessão;
- rotacionar o token CSRF;
- registrar o instante da autenticação;
- aplicar limitação de tentativas.

Exemplo de erro:

```json
{
  "error": "Usuário ou senha inválidos"
}
```

A resposta não deverá diferenciar:

- usuário inexistente;
- senha incorreta;
- usuário desabilitado (`active = 0`);
- usuário excluído logicamente (`deleted_at IS NOT NULL`).

Essas condições deverão utilizar uma resposta genérica de credenciais inválidas, evitando enumeração de usuários e exposição do estado da conta.

## Registro

O endpoint continuará sendo:

```text
POST /auth/register
```

O registro deverá:

- exigir token CSRF;
- validar o nome de usuário;
- validar a senha;
- aplicar `password_hash` com `PASSWORD_DEFAULT`;
- tratar duplicidade de usuário como `409 Conflict`;
- não retornar o hash;
- não iniciar autenticação automaticamente sem decisão explícita.

A decisão inicial será manter o comportamento contratual existente sempre que possível.

Caso o contrato atual autentique automaticamente após registro, essa operação deverá:

- regenerar a sessão;
- gerar novo token CSRF;
- ser coberta por testes de contrato.

## Hash de senha

As senhas serão armazenadas com:

```php
password_hash($password, PASSWORD_DEFAULT);
```

A validação utilizará:

```php
password_verify($password, $storedHash);
```

Após autenticação, o backend poderá verificar:

```php
password_needs_rehash($storedHash, PASSWORD_DEFAULT);
```

Quando necessário, o hash será atualizado de maneira transparente.

Não deverão ser utilizados:

- MD5;
- SHA-1;
- SHA-256 simples;
- criptografia reversível;
- hash manual sem salt apropriado;
- senha em texto puro.

## Logout

O endpoint continuará sendo:

```text
POST /auth/logout
```

O logout deverá exigir:

- sessão válida, quando existente;
- token CSRF válido.

O processo deverá:

1. limpar os dados da sessão;
2. invalidar o cookie;
3. destruir a sessão no servidor;
4. retornar resposta JSON;
5. não manter o token CSRF autenticado anterior.

Exemplo conceitual:

```php
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();
```

O código definitivo deverá permanecer encapsulado em um serviço de sessão.

## Consulta da sessão atual

O endpoint continuará sendo:

```text
GET /auth/me
```

Quando autenticado, retornará somente dados públicos necessários ao frontend.

Exemplo:

```json
{
  "id": 123,
  "username": "usuario"
}
```

Esse formato preserva o contrato existente de `GET /auth/me` e permite que o frontend restaure diretamente o usuário autenticado sem um wrapper adicional.

A resposta de login poderá continuar utilizando o campo `user`, pois constitui um contrato diferente.

Quando não autenticado:

```json
{
  "error": "Autenticação necessária"
}
```

com status:

```text
401 Unauthorized
```

`GET /auth/me` não deverá criar implicitamente uma sessão autenticada.

Uma sessão autenticada somente permanecerá válida enquanto o usuário correspondente existir e estiver disponível.

O usuário será considerado indisponível quando:

- estiver com `active = 0`;
- estiver com `deleted_at IS NOT NULL`.

Caso a sessão aponte para um usuário inexistente ou indisponível, `/auth/me` deverá invalidar a sessão atual e retornar `401 Unauthorized`.

A definição do ciclo de vida do usuário e da política de exclusão lógica permanece centralizada no ADR 0004.

## Tempo de expiração

A sessão terá dois limites:

- tempo máximo de inatividade;
- tempo máximo absoluto.

Valores iniciais propostos:

```text
Inatividade máxima: 30 minutos
Duração absoluta: 8 horas
```

Os valores deverão ser configuráveis.

A cada requisição autenticada válida:

1. verificar `last_activity_at`;
2. verificar `absolute_expires_at`;
3. rejeitar a sessão se qualquer limite tiver expirado;
4. atualizar `last_activity_at` quando a sessão continuar válida.

A expiração não deverá depender somente da coleta automática de lixo do PHP.

Mesmo que o arquivo da sessão ainda exista, a aplicação deverá rejeitar a sessão expirada com base nos timestamps armazenados.

## Sessão expirada

Quando a sessão estiver expirada, o backend deverá:

- limpar a sessão;
- invalidar o cookie quando possível;
- retornar `401 Unauthorized`;
- usar resposta JSON consistente.

Exemplo:

```json
{
  "error": "Sessão expirada"
}
```

O frontend deverá:

- limpar o estado autenticado local;
- redirecionar para login quando apropriado;
- preservar somente dados offline que não representem autenticação;
- não tentar reutilizar indefinidamente a sessão expirada.

## Proteção CSRF

Será utilizado token sincronizador armazenado na sessão.

O token deverá ser gerado com fonte criptograficamente segura.

Exemplo:

```php
$token = bin2hex(random_bytes(32));
```

O token terá pelo menos 256 bits de entropia antes da codificação hexadecimal.

## Endpoint para obtenção do token CSRF

Será criado:

```text
GET /auth/csrf
```

Esse endpoint poderá ser chamado sem autenticação.

Sua função será:

- iniciar ou recuperar uma sessão anônima;
- gerar token quando ausente;
- retornar o token CSRF;
- não retornar dados de usuário;
- não alterar dados de negócio.

Exemplo:

```json
{
  "csrf_token": "token-aleatorio"
}
```

Esse endpoint representa uma adição de segurança ao contrato original e deverá ser documentado no inventário da API.

## Armazenamento do token no frontend

O token CSRF poderá permanecer:

- na memória da aplicação;
- no estado central do frontend sem persistência sensível.

O token CSRF não deverá ser armazenado permanentemente em:

- `localStorage`;
- IndexedDB;
- parâmetros de URL;
- logs;
- cookies acessíveis ao JavaScript;
- analytics;
- mensagens de erro.

Após recarregar a página, o frontend poderá solicitar novo token a `/auth/csrf`.

## Envio do token

Métodos mutáveis deverão enviar:

```http
X-CSRF-Token: token-aleatorio
```

Métodos inicialmente protegidos:

- `POST`;
- `PUT`;
- `PATCH`;
- `DELETE`.

Métodos normalmente isentos:

- `GET`;
- `HEAD`;
- `OPTIONS`.

A isenção pressupõe que métodos seguros não alterem estado persistente.

Um endpoint `GET` não deverá ser utilizado para:

- excluir registros;
- criar registros;
- autenticar usuário;
- encerrar sessão;
- disparar tarefas mutáveis;
- confirmar operações.

## Endpoints protegidos por CSRF

A proteção abrangerá pelo menos:

```text
POST   /auth/register
POST   /auth/login
POST   /auth/logout

POST   /api/contacts
PUT    /api/contacts/:id
DELETE /api/contacts/:id

POST   /api/events
PUT    /api/events/:id
DELETE /api/events/:id
```

Novos endpoints mutáveis deverão ser protegidos por padrão.

Uma exceção exigirá justificativa documentada.

## Validação do token

O middleware deverá:

1. determinar se o método exige proteção;
2. obter o token da sessão;
3. obter o cabeçalho `X-CSRF-Token`;
4. rejeitar valores ausentes;
5. comparar os valores com `hash_equals`;
6. interromper a requisição antes do controller em caso de falha.

Exemplo conceitual:

```php
if (!hash_equals($sessionToken, $requestToken)) {
    throw new CsrfException();
}
```

Não deverá ser utilizada comparação simples quando uma função de comparação segura estiver disponível.

## Resposta para falha CSRF

Uma falha CSRF retornará:

```text
403 Forbidden
```

Exemplo:

```json
{
  "error": "Token CSRF inválido ou ausente"
}
```

A resposta não deverá:

- revelar o token esperado;
- revelar dados da sessão;
- retornar stack trace;
- gerar nova operação automaticamente;
- executar parcialmente o controller.

## Rotação do token CSRF

O token deverá ser rotacionado:

- após login bem-sucedido;
- após alteração relevante da identidade;
- quando uma nova sessão for criada;
- após eventos de segurança que invalidem a sessão.

O token poderá permanecer estável durante uma mesma sessão autenticada para evitar falhas em múltiplas abas.

Uma rotação periódica futura deverá considerar requisições concorrentes e múltiplas abas.

## Login CSRF

O endpoint de login também será protegido.

Fluxo conceitual:

```text
1. Frontend solicita GET /auth/csrf
2. Backend cria sessão anônima e retorna token
3. Frontend envia POST /auth/login com X-CSRF-Token
4. Backend valida o token
5. Backend valida as credenciais
6. Backend regenera o ID da sessão
7. Backend gera novo token CSRF
8. Backend retorna autenticação concluída
```

O frontend deverá substituir o token anterior pelo token retornado após o login ou solicitar novamente `/auth/csrf`.

## Resposta do login

Após login, a resposta poderá incluir:

```json
{
  "user": {
    "id": 123,
    "username": "usuario"
  },
  "csrf_token": "novo-token"
}
```

Isso evita uma janela em que o frontend continue utilizando o token da sessão anterior.

O contrato definitivo deverá ser coberto por testes.

## Defesa adicional por origem

Além do token CSRF, o backend poderá validar, quando presentes:

- cabeçalho `Origin`;
- cabeçalho `Referer`;
- cabeçalhos Fetch Metadata, como `Sec-Fetch-Site`.

Esses mecanismos serão defesa adicional.

Eles não substituirão o token sincronizador.

Requisições com origem explicitamente incompatível poderão ser rejeitadas antes do controller.

## Middleware de autenticação

Rotas privadas deverão utilizar middleware centralizado.

O middleware deverá:

- iniciar ou acessar a sessão;
- verificar se existe usuário autenticado;
- validar os tempos de expiração;
- disponibilizar o ID do usuário para a camada seguinte;
- rejeitar ausência ou expiração com `401`;
- não consultar usuário informado pelo frontend;
- não duplicar regras em cada controller.

## Middleware CSRF

O middleware CSRF deverá ser separado do middleware de autenticação.

Essa separação permite:

- proteger login e registro antes da autenticação;
- testar as responsabilidades isoladamente;
- aplicar CSRF somente aos métodos necessários;
- manter SRP;
- ordenar explicitamente os middlewares.

Ordem conceitual para uma rota autenticada e mutável:

```text
Request
   |
   v
Session middleware
   |
   v
Authentication middleware
   |
   v
CSRF middleware
   |
   v
Controller
```

A ordem definitiva poderá incluir:

- identificação da requisição;
- limite de requisições;
- parsing JSON;
- validação de conteúdo.

## Integração com o frontend

O frontend deverá utilizar um cliente HTTP centralizado.

Exemplo conceitual:

```ts
async function apiFetch(
  input: RequestInfo | URL,
  init: RequestInit = {},
): Promise<Response> {
  return fetch(input, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...init.headers,
    },
  });
}
```

Para métodos mutáveis, o cliente adicionará:

```http
X-CSRF-Token
Content-Type: application/json
```

Os componentes React não deverão implementar individualmente:

- obtenção do token;
- cabeçalho CSRF;
- tratamento de sessão expirada;
- parsing repetido de erros;
- URLs absolutas da API.

## Estado offline

Dados mantidos em IndexedDB não representarão autenticação.

A existência de dados offline não deverá fazer o frontend considerar o usuário autenticado.

Quando a sessão expirar:

- os dados offline poderão permanecer no dispositivo;
- operações de sincronização deverão aguardar nova autenticação;
- nenhuma requisição protegida deverá ser tratada como autenticada;
- a interface deverá informar a necessidade de login.

A política definitiva para dados locais de múltiplos usuários deverá ser definida na etapa de sincronização.

## Limitação de tentativas

Login e registro deverão possuir limitação de requisições.

A limitação poderá considerar:

- endereço IP;
- nome de usuário normalizado;
- janela de tempo;
- quantidade de falhas;
- bloqueio temporário.

A limitação não deverá depender exclusivamente da sessão, pois um atacante pode descartar cookies.

Os detalhes serão definidos na implementação de segurança e rate limiting.

## Logs de segurança

Eventos relevantes poderão ser registrados:

- login bem-sucedido;
- login recusado;
- sessão expirada;
- logout;
- falha CSRF;
- tentativa de usar sessão inválida;
- excesso de tentativas.

Os logs não deverão conter:

- senha;
- hash de senha;
- cookie de sessão;
- token CSRF completo;
- chave da OpenWeather;
- conteúdo privado desnecessário.

## Tratamento de erros

Status principais:

```text
400 Bad Request          JSON inválido ou entrada inválida
401 Unauthorized         ausência ou expiração de autenticação
403 Forbidden            token CSRF inválido ou operação não autorizada
409 Conflict             nome de usuário duplicado
429 Too Many Requests    limite de tentativas excedido
500 Internal Server Error erro interno não exposto
```

As mensagens JSON deverão seguir o formato:

```json
{
  "error": "Mensagem segura"
}
```

## Testes obrigatórios

A implementação deverá possuir testes para:

- obtenção do token CSRF;
- token ausente;
- token incorreto;
- token correto;
- login com credenciais válidas;
- login com credenciais inválidas;
- regeneração do ID após login;
- rotação do token após login;
- logout com token válido;
- logout sem token;
- sessão inexistente;
- sessão inativa;
- sessão absolutamente expirada;
- isolamento por usuário;
- cookie com atributos corretos;
- método `GET` sem alteração de estado;
- resposta JSON para falha de autenticação;
- resposta JSON para falha CSRF.

## Consequências positivas

- autenticação compatível com hospedagem compartilhada;
- dados da sessão armazenados no servidor;
- cookie inacessível ao JavaScript;
- proteção contra fixação de sessão;
- proteção CSRF explícita;
- ausência de JWT no `localStorage`;
- revogação imediata no logout;
- middleware reutilizável;
- integração coerente com mesma origem;
- expiração validada pela aplicação;
- menor complexidade operacional.

## Consequências negativas

- exige armazenamento de sessões no servidor;
- exige endpoint adicional para token CSRF;
- o frontend precisa gerenciar token em memória;
- múltiplas abas precisam conviver com rotação de token;
- a coleta de sessões antigas depende da configuração do PHP;
- futura implantação distribuída exigirá outro armazenamento;
- sessões anônimas poderão ser criadas antes do login;
- testes precisam simular cookies e sessão.

## Restrições

A decisão não autoriza:

- armazenar senha na sessão;
- armazenar cookie de sessão no `localStorage`;
- utilizar JWT apenas por conveniência;
- desabilitar CSRF por estar na mesma origem;
- aceitar `user_id` enviado pelo frontend como proprietário;
- manter o mesmo ID de sessão após login;
- expor token CSRF em URL;
- aceitar métodos `GET` que alterem estado;
- utilizar `Access-Control-Allow-Origin: *` em rotas autenticadas;
- registrar cookies ou tokens completos;
- depender somente do garbage collector para expiração;
- usar MD5 ou SHA simples para senhas.

## Critérios de revisão futura

A decisão deverá ser reavaliada caso:

- frontend e backend passem a usar origens diferentes;
- seja criado aplicativo móvel nativo;
- a API passe a atender integrações externas;
- a aplicação seja distribuída em múltiplos servidores;
- seja adotado Redis ou outro armazenamento de sessão;
- seja necessária autenticação federada;
- seja implementado login por provedor externo;
- seja criada API pública;
- os requisitos de duração da sessão mudem;
- a hospedagem compartilhada seja substituída.

## Relação com outros documentos

Este ADR complementa:

- `docs/architecture/shared-hosting-backend-plan.md`;
- `docs/architecture/adr/0001-arquitetura-backend-php.md`;
- `docs/architecture/adr/0002-topologia-producao-mesma-origem.md`;
- `docs/migration/api-inventory.md`;
- `docs/security/dependency-risk-register.md`.

## Próximas decisões

Após a aceitação deste ADR, deverão ser definidos:

1. modelo de dados de eventos e contatos;
2. cache meteorológico;
3. configuração e armazenamento de segredos;
4. estratégia de sincronização;
5. regras definitivas de implantação no Apache.

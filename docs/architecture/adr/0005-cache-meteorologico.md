# ADR 0005 — Cache meteorológico persistente

## Status

Aceito.

## Data

2026-08-06.

## Contexto

A Agenda Inteligente consulta dados meteorológicos da OpenWeather por meio do backend.

O contrato HTTP atual possui três endpoints autenticados:

- `GET /api/weather/current`;
- `GET /api/weather/forecast`;
- `GET /api/weather/event`.

O inventário da aplicação-fonte registra:

- cache de 30 minutos;
- cache separado para clima atual e previsão;
- compartilhamento da previsão entre a página de clima e a consulta de clima do evento;
- limite de 200 entradas;
- normalização da cidade;
- deduplicação de chamadas simultâneas;
- timeout de 10 segundos para a OpenWeather;
- unidades métricas;
- idioma português;
- limite de 120 caracteres para o nome da cidade.

No backend Node.js, o cache é mantido em memória com estruturas `Map`.

Essa estratégia funciona enquanto existe um processo backend permanentemente ativo.

O backend PHP será executado em hospedagem compartilhada, onde cada requisição possui ciclo de vida independente e não existe garantia de que uma estrutura em memória continue disponível entre requisições.

Também podem existir vários processos PHP atendendo requisições simultâneas.

Por esse motivo, um cache apenas em memória não preservaria de forma confiável:

- o TTL entre requisições;
- o compartilhamento entre usuários;
- o compartilhamento entre processos PHP;
- o limite global de entradas;
- a deduplicação de atualizações simultâneas.

A migration provisória já contém uma tabela simples chamada `weather_cache`, mas sua estrutura ainda precisa ser validada e alinhada à estratégia definitiva.

## Problema

Definir como o backend PHP deverá armazenar, consultar, atualizar, invalidar e limpar o cache meteorológico de forma compatível com:

- PHP 8.2;
- MySQL ou MariaDB;
- hospedagem compartilhada;
- ausência de processos residentes;
- execução concorrente de requisições;
- contrato HTTP já consumido pelo frontend;
- limite de chamadas da OpenWeather;
- segurança da chave privada;
- manutenção simples.

A decisão também deve estabelecer:

- o que forma uma chave de cache;
- quais endpoints compartilham dados;
- qual conteúdo será persistido;
- como o TTL será calculado;
- como evitar o efeito manada;
- o que acontece quando o provedor está indisponível;
- como limitar o número de entradas;
- como realizar a limpeza;
- quais dados não podem ser armazenados.

## Requisitos

A solução deverá:

- manter o TTL principal de 30 minutos;
- compartilhar o cache entre processos PHP;
- compartilhar o cache entre usuários;
- não armazenar a chave da OpenWeather;
- não expor a chave ao frontend;
- preservar os formatos atuais das respostas;
- impedir que respostas inválidas sejam armazenadas;
- distinguir clima atual de previsão;
- reutilizar a previsão em `/api/weather/forecast` e `/api/weather/event`;
- tratar cidades equivalentes como a mesma chave;
- utilizar timestamps técnicos em UTC;
- limitar o cache a aproximadamente 200 entradas;
- permitir limpeza por cron do cPanel;
- continuar funcionando quando o cron atrasar;
- reduzir chamadas simultâneas duplicadas;
- manter o tratamento de erros definido no inventário da API;
- ser testável sem depender da OpenWeather real.

## Alternativas consideradas

## Alternativa 1 — Cache somente em memória PHP

Cada processo PHP manteria um array estático ou outra estrutura em memória.

### Vantagens

- implementação simples;
- leitura rápida;
- ausência de consultas ao banco;
- comportamento parecido com o `Map` utilizado no Node.js.

### Desvantagens

- o cache pode desaparecer ao final da requisição;
- processos PHP diferentes não compartilham valores;
- o limite de 200 entradas não é global;
- requisições simultâneas podem repetir chamadas externas;
- o comportamento varia conforme o modo de execução do PHP;
- não é confiável em hospedagem compartilhada.

### Avaliação

Rejeitada como mecanismo principal.

Uma pequena memória local poderá ser usada apenas dentro da mesma requisição, sem substituir o cache persistente.

## Alternativa 2 — Cache em arquivos

Cada resposta seria gravada em arquivo no servidor.

### Vantagens

- persiste entre requisições;
- não depende do banco;
- pode utilizar `flock`;
- implantação relativamente simples.

### Desvantagens

- exige diretório gravável corretamente protegido;
- pode sofrer problemas de permissão;
- limpeza e limitação exigem varredura de arquivos;
- nomes de arquivos precisam ser protegidos;
- pode gerar muitos arquivos;
- consistência e implantação variam entre provedores;
- não aproveita o MySQL já necessário pela aplicação;
- pode dificultar futura execução em mais de um servidor.

### Avaliação

Possível, mas não escolhida.

Poderá ser usada futuramente como contingência caso o banco não esteja disponível para cache, mas não será a estratégia inicial.

## Alternativa 3 — Cache em Redis ou Memcached

A aplicação utilizaria um serviço dedicado de cache.

### Vantagens

- alta performance;
- TTL nativo;
- operações atômicas;
- mecanismos adequados para locks;
- limpeza automática;
- boa escalabilidade.

### Desvantagens

- normalmente indisponível na hospedagem compartilhada;
- exige serviço adicional;
- aumenta o custo operacional;
- cria dependência de infraestrutura externa;
- dificulta a implantação pretendida.

### Avaliação

Rejeitada para o ambiente atual.

Poderá ser reconsiderada se a aplicação migrar para VPS, nuvem ou infraestrutura que ofereça o serviço.

## Alternativa 4 — Cache persistente no MySQL ou MariaDB

Os dados meteorológicos serão armazenados em uma tabela compartilhada.

### Vantagens

- persiste entre requisições;
- é compartilhado entre processos PHP;
- utiliza infraestrutura já necessária;
- permite TTL explícito;
- permite índices para expiração;
- permite limitação e limpeza por SQL;
- pode participar de testes de integração;
- é compatível com hospedagem compartilhada;
- permite coordenação concorrente por lock do banco.

### Desvantagens

- adiciona consultas ao banco;
- exige rotina de limpeza;
- exige serialização e validação JSON;
- uma estratégia de concorrência precisa ser implementada;
- o banco passa a atender também leituras de cache.

### Avaliação

Escolhida.

O volume esperado é pequeno e o limite de 200 entradas mantém o custo de armazenamento e consulta reduzido.

## Decisão

Adotar cache meteorológico persistente no MySQL ou MariaDB.

O cache será:

- global para a aplicação;
- compartilhado entre usuários autenticados;
- compartilhado entre processos PHP;
- separado por tipo de recurso;
- persistido em JSON;
- válido por 30 minutos;
- limitado a 200 entradas;
- limpo de forma oportunística e por cron;
- protegido contra atualizações simultâneas por cidade e tipo.

Não será utilizado `user_id` na tabela de cache.

Dados meteorológicos por cidade não pertencem a um usuário específico e podem ser compartilhados com segurança entre usuários.

## Tipos de cache

A implementação inicial terá dois tipos:

```text
current
forecast
```

### `current`

Armazena os dados necessários para produzir a resposta de:

```text
GET /api/weather/current
```

### `forecast`

Armazena a previsão completa necessária para produzir as respostas de:

```text
GET /api/weather/forecast
GET /api/weather/event
```

O endpoint de clima do evento não terá uma entrada própria.

Ele deverá derivar sua resposta da mesma previsão usada pela página de clima.

Isso preserva o comportamento atual em que previsão diária e evento compartilham uma única consulta externa por cidade durante o TTL.

## Conteúdo persistido

O cache não armazenará necessariamente a resposta HTTP final do endpoint.

Ele armazenará um payload interno normalizado, suficiente para que o service produza o contrato de cada endpoint.

### Clima atual

O payload deverá preservar as informações necessárias para produzir:

- cidade;
- temperatura;
- sensação térmica;
- umidade;
- velocidade do vento;
- descrição;
- ícone;
- condição;
- indicação de dia ou noite;
- instante observado;
- deslocamento de fuso;
- horário observado.

### Previsão

O payload deverá preservar os intervalos de previsão necessários para:

- retornar até cinco dias;
- selecionar o intervalo mais próximo de 12h no horário local;
- selecionar o intervalo mais próximo do horário de um evento;
- selecionar o próximo intervalo disponível no dia atual;
- identificar eventos fora da janela;
- identificar datas passadas;
- apresentar temperatura, condição, descrição e ícone.

A seleção específica de um evento não será persistida.

Ela será calculada a partir do payload de previsão no momento da requisição.

## Estrutura da tabela

Estrutura conceitual:

```sql
CREATE TABLE weather_cache (
    cache_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    cache_type VARCHAR(20) NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'openweather',
    schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    city_key VARCHAR(120) NOT NULL,
    city_query VARCHAR(120) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_weather_cache_expires_at (expires_at),
    KEY idx_weather_cache_type_city (
        cache_type,
        city_key
    ),
    KEY idx_weather_cache_updated_at (updated_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

## Compatibilidade do JSON

`payload_json` será `LONGTEXT` para reduzir diferenças entre versões de MySQL e MariaDB disponíveis em hospedagens compartilhadas.

O PHP deverá:

- serializar com `json_encode`;
- verificar falhas de serialização;
- desserializar com `json_decode`;
- lançar erro quando o conteúdo não for JSON válido;
- validar a estrutura interna;
- remover entradas corrompidas.

O uso futuro de um tipo nativo `JSON` poderá ser avaliado após confirmar a versão real do banco de produção.

A chave da OpenWeather nunca será incluída no JSON.

## Chave de cache

A chave primária será um SHA-256 hexadecimal de uma descrição canônica.

Exemplo conceitual da descrição:

```text
openweather|v1|forecast|sao jose dos campos|metric|pt
```

Resultado armazenado:

```text
sha256(descrição canônica)
```

O uso do hash:

- mantém a chave em tamanho fixo;
- evita problemas com caracteres especiais;
- permite incluir versão, tipo, unidade e idioma;
- facilita invalidação futura por mudança de versão;
- não expõe a chave da API.

A descrição deverá incluir, no mínimo:

- provedor;
- versão do schema interno;
- tipo `current` ou `forecast`;
- cidade normalizada;
- unidades;
- idioma.

## Normalização da cidade

Antes de consultar ou gravar o cache, o backend deverá:

1. confirmar que a cidade é uma string;
2. remover espaços nas extremidades;
3. rejeitar valor vazio;
4. rejeitar valores acima de 120 caracteres;
5. reduzir sequências internas de espaços;
6. normalizar aliases conhecidos;
7. remover diacríticos para formar `city_key`;
8. converter a chave para minúsculas de forma consistente.

Exemplos que deverão produzir a mesma chave:

```text
São José dos Campos
sao jose dos campos
  São   José dos Campos
```

O valor enviado ao provedor poderá preservar uma forma amigável ou utilizar o alias reconhecido.

A normalização usada na chave não deverá alterar o nome retornado no contrato HTTP quando o provedor fornecer uma forma oficial.

## TTL

O TTL principal será:

```text
30 minutos
```

O instante de expiração será calculado após uma resposta externa válida:

```text
expires_at = fetched_at + 30 minutos
```

Todos os timestamps técnicos serão armazenados em UTC.

Uma entrada é fresca quando:

```text
expires_at > UTC_TIMESTAMP()
```

A expiração não depende do horário local da cidade consultada.

## Fluxo de leitura

Para cada requisição de clima:

1. validar autenticação;
2. validar e normalizar a cidade;
3. calcular a chave;
4. consultar a entrada;
5. desserializar e validar o payload;
6. retornar o payload quando estiver fresco;
7. iniciar o fluxo de atualização quando estiver ausente, inválido ou expirado.

Um hit de cache não deverá realizar chamada à OpenWeather.

## Fluxo de atualização

Quando não existir uma entrada fresca:

1. tentar adquirir o lock correspondente à chave;
2. após adquirir o lock, consultar novamente o cache;
3. retornar a entrada caso outro processo já a tenha atualizado;
4. consultar a OpenWeather quando ainda for necessário;
5. validar o status HTTP;
6. validar o payload recebido;
7. normalizar a estrutura interna;
8. gravar por `INSERT ... ON DUPLICATE KEY UPDATE`;
9. definir `fetched_at` e `expires_at`;
10. executar limpeza oportunística;
11. liberar o lock em bloco `finally`;
12. produzir a resposta HTTP.

A segunda leitura após a aquisição do lock é obrigatória.

Ela evita repetir uma chamada externa que tenha sido concluída por outro processo enquanto a requisição aguardava.

## Concorrência e efeito manada

A aplicação utilizará lock nomeado do MySQL ou MariaDB por chave de cache.

Exemplo conceitual:

```sql
SELECT GET_LOCK(:lock_name, 12);
```

O nome deverá ser derivado do hash da chave e respeitar o limite do banco.

O tempo de espera deverá considerar:

- timeout externo de 10 segundos;
- pequena margem para persistência;
- limite total de execução do PHP.

Após o uso:

```sql
SELECT RELEASE_LOCK(:lock_name);
```

O release deverá ocorrer em `finally`, inclusive quando houver erro.

O lock não substituirá a chave primária nem o `UPSERT`.

Caso o ambiente de produção não permita locks nomeados, a aplicação deverá:

- registrar a limitação;
- continuar protegida pela chave primária e pelo `UPSERT`;
- aceitar temporariamente chamadas externas duplicadas;
- não falhar apenas porque o lock não pôde ser adquirido;
- reavaliar uma estratégia de lease em tabela própria.

Não será mantida uma transação SQL aberta durante a chamada HTTP externa.

## Timeout externo

A chamada à OpenWeather terá timeout máximo de:

```text
10 segundos
```

No PHP, o cliente HTTP deverá limitar:

- tempo de conexão;
- tempo total;
- quantidade de redirecionamentos;
- tamanho aceitável da resposta.

Um timeout deverá continuar produzindo:

```text
504 Gateway Timeout
```

quando não houver cache reutilizável.

## Uso de valor expirado em falha externa

Será adotada uma janela controlada de `stale-if-error`.

Uma entrada expirada poderá ser reutilizada somente quando:

- o payload estiver íntegro;
- a chamada externa falhar por problema transitório;
- a idade total desde `fetched_at` não ultrapassar duas horas.

Problemas transitórios incluem:

- timeout;
- falha de conexão;
- erro `5xx` do provedor;
- limite temporário `429`.

Não será utilizado valor expirado quando:

- a cidade for inválida;
- a cidade não for encontrada;
- a chave estiver ausente;
- a chave for inválida;
- o payload local estiver corrompido;
- a idade total ultrapassar duas horas.

Quando um valor expirado for utilizado:

- a resposta continuará preservando o corpo atual;
- o backend poderá enviar `Warning: 110 - Response is Stale`;
- o backend poderá enviar `X-Weather-Cache: stale`;
- o evento deverá ser registrado em log sem incluir segredos.

Os cabeçalhos são informativos e não passam a fazer parte obrigatória do contrato consumido pelo frontend.

## Erros e cache

Respostas de erro não serão armazenadas.

Não haverá cache negativo inicial para:

- cidade inexistente;
- chave inválida;
- limite excedido;
- falha de conexão;
- timeout;
- resposta incompleta.

Quando não existir entrada utilizável, serão preservados os códigos definidos no inventário:

- `400` para entrada inválida;
- `404` para cidade não encontrada;
- `502` para falha do provedor ou resposta incompleta;
- `503` para chave ausente, chave inválida ou limite temporário;
- `504` para timeout.

## Gravação

A persistência utilizará operação atômica equivalente a:

```sql
INSERT INTO weather_cache (
    cache_key,
    cache_type,
    provider,
    schema_version,
    city_key,
    city_query,
    payload_json,
    fetched_at,
    expires_at
) VALUES (
    :cache_key,
    :cache_type,
    :provider,
    :schema_version,
    :city_key,
    :city_query,
    :payload_json,
    :fetched_at,
    :expires_at
)
ON DUPLICATE KEY UPDATE
    cache_type = VALUES(cache_type),
    provider = VALUES(provider),
    schema_version = VALUES(schema_version),
    city_key = VALUES(city_key),
    city_query = VALUES(city_query),
    payload_json = VALUES(payload_json),
    fetched_at = VALUES(fetched_at),
    expires_at = VALUES(expires_at),
    updated_at = CURRENT_TIMESTAMP;
```

A sintaxe final deverá ser validada contra a versão real de MySQL ou MariaDB da produção.

A aplicação não deverá apagar uma entrada ainda utilizável antes de concluir a nova consulta.

## Limite de entradas

O objetivo inicial é manter no máximo:

```text
200 entradas
```

Após uma gravação bem-sucedida, a aplicação deverá:

1. excluir entradas vencidas há mais de duas horas;
2. contar as entradas restantes;
3. quando houver mais de 200, excluir as mais antigas por `updated_at`;
4. nunca remover a entrada que acabou de ser gravada antes de produzir a resposta.

O limite é operacional e não precisa ser aplicado por constraint.

Pequenas ultrapassagens temporárias durante concorrência são aceitáveis, desde que a limpeza posterior restaure o limite.

## Limpeza oportunística

A aplicação deverá executar uma limpeza leve após gravações bem-sucedidas.

A limpeza não será executada em todos os hits.

Falha na limpeza:

- não invalidará uma resposta meteorológica válida;
- deverá ser registrada;
- será corrigida pelo cron posterior.

## Limpeza por cron

Será criado um comando PHP executável pelo cron do cPanel.

Exemplo conceitual:

```text
php backend/bin/cleanup-weather-cache.php
```

Periodicidade inicial:

```text
a cada hora
```

O comando deverá:

- excluir entradas com mais de duas horas desde `fetched_at`;
- remover excedentes acima de 200;
- emitir código de saída diferente de zero em falha;
- não imprimir payloads;
- não imprimir a chave da OpenWeather;
- permitir execução repetida com segurança.

O funcionamento do cache não dependerá exclusivamente do cron.

Mesmo que o cron atrase, o TTL continuará sendo verificado durante a leitura e a limpeza oportunística continuará disponível.

## Cabeçalhos de cache HTTP

O cache decidido neste ADR é um cache interno do backend.

Ele não autoriza cache público das respostas autenticadas.

Os endpoints deverão utilizar política compatível com conteúdo autenticado, por exemplo:

```http
Cache-Control: no-store
```

O cache interno poderá ser observado por cabeçalho técnico opcional:

```text
X-Weather-Cache: hit
X-Weather-Cache: miss
X-Weather-Cache: stale
```

Esses cabeçalhos não deverão revelar:

- chave da API;
- URL completa com segredo;
- conteúdo interno do lock;
- stack trace;
- credenciais de banco.

## Segurança

A chave `OPENWEATHER_API_KEY`:

- ficará apenas na configuração privada do backend;
- não será incluída no frontend;
- não será incluída na chave do cache;
- não será incluída no payload;
- não será escrita em logs;
- não será persistida no banco;
- não será incluída em mensagens de erro.

O cache armazenará apenas dados meteorológicos públicos e metadados técnicos.

Não serão armazenados:

- identificadores de sessão;
- identificadores de usuário;
- cookies;
- cabeçalhos de autenticação;
- IP do usuário;
- parâmetros privados;
- segredos.

## Separação de responsabilidades

### `WeatherController`

Responsável por:

- receber parâmetros HTTP;
- acionar validação de entrada;
- chamar o service;
- produzir a resposta JSON;
- mapear o resultado para o contrato do endpoint.

Não deverá:

- executar SQL;
- montar URL com chave diretamente;
- controlar locks;
- decidir a estrutura da tabela.

### `WeatherService`

Responsável por:

- coordenar cache e provedor;
- definir o tipo de cache;
- aplicar seleção de previsão;
- aplicar `stale-if-error`;
- preservar o contrato de domínio;
- coordenar logs técnicos.

### `WeatherCacheRepository`

Responsável por:

- buscar entrada;
- gravar por `UPSERT`;
- excluir entrada corrompida;
- executar limpeza;
- limitar quantidade;
- adquirir e liberar locks do banco.

Não deverá chamar a OpenWeather.

### `OpenWeatherClient`

Responsável por:

- carregar a chave pela configuração;
- montar a requisição externa;
- aplicar timeout;
- interpretar erros do provedor;
- validar a resposta mínima;
- devolver estrutura interna normalizada.

Não deverá conhecer sessão, controller ou resposta HTTP final.

## Observabilidade

Os logs poderão registrar:

- tipo de cache;
- hash ou prefixo seguro da chave;
- cidade normalizada;
- hit;
- miss;
- stale;
- tempo da chamada externa;
- status externo;
- timeout;
- falha de desserialização;
- falha de lock;
- quantidade removida na limpeza.

Os logs não deverão registrar:

- chave da OpenWeather;
- URL externa completa contendo `appid`;
- cookies;
- sessão;
- payload completo sem necessidade;
- stack trace enviado ao frontend.

## Testes obrigatórios

## Testes de chave e normalização

Validar que:

- cidades equivalentes geram a mesma chave;
- espaços extras são normalizados;
- diacríticos não duplicam entradas;
- `current` e `forecast` geram chaves diferentes;
- idioma e unidade participam da versão da chave;
- cidade vazia é rejeitada;
- cidade acima de 120 caracteres é rejeitada.

## Testes de TTL

Validar que:

- uma entrada fresca é reutilizada;
- uma entrada expirada exige atualização;
- o TTL é de 30 minutos;
- `fetched_at` e `expires_at` usam UTC;
- o limite exato da expiração é tratado de forma consistente.

## Testes de compartilhamento

Validar que:

- usuários diferentes reutilizam a mesma entrada;
- `/api/weather/forecast` e `/api/weather/event` reutilizam a mesma previsão;
- `/api/weather/current` não reutiliza indevidamente a previsão;
- processos ou conexões diferentes enxergam a mesma entrada persistida.

## Testes de concorrência

Validar que:

- duas requisições simultâneas para a mesma chave produzem uma única chamada externa quando o lock estiver disponível;
- o segundo processo relê o cache após adquirir o lock;
- o lock é liberado após sucesso;
- o lock é liberado após exceção;
- falha do mecanismo de lock não corrompe o cache;
- o `UPSERT` mantém apenas uma linha por chave.

## Testes de payload

Validar que:

- JSON válido é desserializado;
- JSON inválido é removido;
- payload incompleto não é servido;
- resposta externa inválida não é persistida;
- o formato final dos três endpoints permanece compatível.

## Testes de falha externa

Validar que:

- timeout sem cache retorna `504`;
- erro transitório sem cache preserva o erro previsto;
- erro transitório com entrada de até duas horas pode servir stale;
- entrada acima de duas horas não é servida;
- `404` de cidade não usa stale;
- chave inválida não usa stale;
- erros não são gravados como cache.

## Testes de limpeza

Validar que:

- entradas antigas são removidas;
- o limite retorna a 200 entradas;
- a entrada recém-gravada não é removida indevidamente;
- o comando cron é idempotente;
- falha de limpeza não invalida resposta já obtida.

## Testes de segurança

Validar que:

- a chave não aparece em `payload_json`;
- a chave não aparece em `cache_key`;
- a chave não aparece nos logs;
- a chave não aparece no bundle do frontend;
- as rotas continuam protegidas por sessão;
- respostas de erro não expõem detalhes internos.

## Migração

O cache em memória da aplicação-fonte não precisa ser migrado.

A implantação deverá:

1. criar ou corrigir a tabela `weather_cache`;
2. implementar `WeatherCacheRepository`;
3. implementar o cliente da OpenWeather;
4. implementar o service;
5. preservar os três endpoints;
6. habilitar testes com relógio controlado;
7. configurar o comando de limpeza;
8. configurar o cron após validação;
9. remover dependência do cache em memória como mecanismo global;
10. validar que a chave não está no frontend.

A tabela provisória existente deverá ser ajustada antes da implantação.

Diferenças principais da decisão:

- chave fixa SHA-256;
- tipo de cache explícito;
- provedor explícito;
- versão do schema;
- cidade normalizada e cidade de consulta;
- payload em `LONGTEXT`;
- instante de coleta;
- expiração;
- índices para tipo, cidade e limpeza.

## Consequências positivas

- cache compartilhado entre processos PHP;
- menor consumo da cota da OpenWeather;
- menor latência em consultas repetidas;
- preservação do comportamento de 30 minutos;
- compartilhamento entre previsão e evento;
- funcionamento compatível com hospedagem compartilhada;
- possibilidade de limpeza e inspeção controlada;
- ausência de segredo no frontend;
- maior previsibilidade em concorrência;
- possibilidade de fallback temporário controlado.

## Consequências negativas

- aumento de consultas ao banco;
- necessidade de tabela e repository;
- necessidade de cron;
- necessidade de validar compatibilidade de locks;
- armazenamento de JSON no banco;
- maior complexidade que um `Map` em memória;
- necessidade de testes de relógio e concorrência;
- possibilidade de servir dado com até duas horas em falha transitória.

## Riscos

### Banco indisponível

Sem banco, o cache persistente não poderá ser consultado.

A primeira implementação não criará automaticamente um segundo cache persistente em arquivo.

O erro deverá ser tratado sem expor credenciais.

### Lock não suportado

O ambiente poderá limitar `GET_LOCK`.

Nesse caso, o sistema continuará consistente pelo `UPSERT`, mas poderá realizar chamadas externas duplicadas.

### Payload incompatível

Mudanças na resposta da OpenWeather podem tornar uma entrada inválida.

Por isso, a versão participa da chave e o payload é validado antes do uso.

### Crescimento do cache

Falhas de cron podem acumular entradas.

A limpeza oportunística e o limite após gravação reduzem esse risco.

### Dado stale

O fallback pode apresentar informação mais antiga.

A janela máxima de duas horas e os cabeçalhos técnicos tornam o comportamento controlado.

## Restrições

Esta decisão não autoriza:

- armazenar a chave da OpenWeather;
- expor dados do cache diretamente sem validação;
- transformar o cache em fonte permanente;
- manter respostas indefinidamente;
- usar cache público para endpoints autenticados;
- criar uma linha por usuário;
- criar cache próprio para cada consulta de evento;
- persistir erros do provedor;
- abrir transação longa durante chamada externa;
- depender exclusivamente do cron;
- usar Redis sem nova decisão de infraestrutura.

## Critérios de revisão futura

A decisão deverá ser reavaliada caso:

- a aplicação migre para VPS ou nuvem;
- Redis ou Memcached se torne disponível;
- o volume ultrapasse o adequado ao MySQL;
- a OpenWeather altere limites ou contrato;
- sejam adicionados novos provedores;
- sejam adicionados dados meteorológicos por coordenadas;
- sejam necessárias atualizações em segundo plano;
- o stale de duas horas deixe de ser aceitável;
- a aplicação seja implantada em múltiplos bancos independentes;
- locks nomeados não estejam disponíveis e a duplicação se torne relevante.

## Relação com outros documentos

Este ADR complementa:

- `docs/architecture/shared-hosting-backend-plan.md`;
- `docs/architecture/adr/0001-arquitetura-backend-php.md`;
- `docs/architecture/adr/0002-topologia-producao-mesma-origem.md`;
- `docs/architecture/adr/0003-sessao-php-e-protecao-csrf.md`;
- `docs/architecture/adr/0004-modelo-eventos-contatos.md`;
- `docs/migration/api-inventory.md`;
- `database/migrations/001_initial_schema.sql`.

## Próxima decisão

Após a aceitação deste ADR, deverá ser registrado:

1. ADR 0006 — estratégia de configuração e segredos.

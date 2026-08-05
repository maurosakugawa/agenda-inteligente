# Planejamento e decisões arquiteturais

## 1. Objetivo

Adaptar a Plataforma Unificada para execução em hospedagem compartilhada tradicional, sem exigir processo Node.js permanente, VPS, Docker, PM2, proxy reverso ou armazenamento PGlite persistente.

## 2. Arquitetura aprovada

```text
Navegador
   |
   v
React + TypeScript + Vite
(build estático)
   |
   | HTTPS /api
   v
PHP 8.2
   |
   v
MySQL ou MariaDB
```

## 3. Decisões vigentes

### ADR-001 — Preservar o frontend React

**Decisão:** manter React, TypeScript, Vite, Zustand, React Router, Tailwind e DaisyUI.

**Motivo:** o frontend compilado é estático e compatível com Apache em hospedagem compartilhada. Reescrevê-lo em PHP não resolve a limitação de infraestrutura e geraria retrabalho.

### ADR-002 — Substituir Express por PHP 8.2

**Decisão:** reimplementar a API e as regras de negócio em PHP 8.2 modular.

**Motivo:** PHP é executado sob demanda pelo webserver e não depende de processo residente.

### ADR-003 — Substituir PGlite por MySQL/MariaDB

**Decisão:** usar o banco relacional oferecido pela hospedagem.

**Motivo:** PGlite exige diretório persistente e cuidados operacionais incompatíveis com o ambiente-alvo.

### ADR-004 — Usar sessão PHP

**Decisão:** autenticar com sessão PHP e cookie `Secure`, `HttpOnly` e `SameSite=Lax`.

**Motivo:** frontend e API operarão na mesma origem; não há benefício em armazenar JWT no navegador.

### ADR-005 — Preservar o contrato da API

**Decisão:** manter, sempre que possível, rotas, payloads, códigos HTTP e formatos de resposta existentes.

**Motivo:** reduz o impacto no frontend e permite migração incremental por módulo.

### ADR-006 — Separar responsabilidades

O backend será organizado em:

- `Controllers`: protocolo HTTP;
- `Services`: regras de negócio e transações;
- `Repositories`: SQL e persistência;
- `Middleware`: autenticação, CSRF e políticas HTTP;
- `Infrastructure`: banco, logs e integrações externas;
- `Support`: validação e utilitários sem regra de negócio.

Controllers não devem conter SQL. Repositories não devem decidir respostas HTTP.

### ADR-007 — Segredos fora do repositório e do webroot

Credenciais reais nunca serão versionadas. O arquivo `backend/config/app.php` será local e ignorado pelo Git. A produção deve manter a configuração privada fora de `public_html` sempre que a hospedagem permitir.

### ADR-008 — Publicação por build local

Node.js será usado apenas no desenvolvimento e na geração do frontend:

```bash
npm ci
npm run build
```

O servidor receberá os arquivos estáticos do `dist` e a API PHP.

## 4. Escopo da migração

1. inventariar o contrato do backend Express atual;
2. criar esquema MySQL equivalente;
3. implementar o núcleo HTTP PHP;
4. migrar autenticação;
5. migrar contatos;
6. migrar eventos e participantes;
7. migrar dashboard e clima;
8. adaptar o frontend para sessão PHP e `/api` na mesma origem;
9. criar testes de regressão e roteiro de publicação.

## 5. Fora do escopo inicial

- reescrever o frontend em PHP;
- alterar significativamente UX/UI;
- introduzir framework PHP pesado;
- adicionar WebSockets ou workers permanentes;
- aproveitar a migração para mudanças funcionais não essenciais;
- migrar IDs sem necessidade comprovada.

## 6. Regras de segurança

- PDO com prepared statements e emulação desativada;
- `password_hash()` e `password_verify()`;
- regeneração de sessão após autenticação;
- CSRF em operações de escrita autenticadas;
- validação no servidor;
- autorização por proprietário em todas as consultas;
- respostas sem stack trace em produção;
- HTTPS obrigatório;
- rate limit e bloqueio temporário no login;
- logs sem senhas, tokens ou segredos.

## 7. Critérios de aceite da migração

A nova implementação somente substituirá a anterior quando:

- autenticação, logout e recuperação de sessão funcionarem;
- CRUD de contatos e eventos tiver paridade funcional;
- participantes forem vinculados corretamente;
- clima e cache funcionarem sem expor a chave externa;
- rotas diretas da SPA abrirem no Apache;
- usuários não acessarem registros de terceiros;
- o build não contiver `localhost:3101` nem chave privada;
- testes críticos e checklist de homologação estiverem aprovados.

## 8. Governança

Qualquer mudança em uma decisão deste documento deve registrar:

- contexto novo;
- decisão anterior;
- nova decisão;
- alternativas consideradas;
- impacto e plano de migração;
- data e responsável.

A documentação detalhada em Word permanece como material complementar. Este Markdown é a referência versionada no repositório.

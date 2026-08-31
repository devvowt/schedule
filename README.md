# Schedule Service

Microserviço de agendamento de tarefas. Cadastre uma URL para ser chamada ou um
comando para ser executado, defina o cron, e escolha **como** a tarefa concorre
com as outras do mesmo horário: em paralelo (Octane) ou em fila (uma após a
outra, com defasagem opcional).

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8 · Redis · Laravel Octane (Swoole) · Docker

---

# 1. O problema que ele resolve

Cinco tarefas marcadas para as 09:00 sobem juntas num cron comum e brigam pelo
mesmo recurso. Aqui isso é uma decisão de cadastro:

| Modo | O que acontece às 09:00 | Onde roda |
|---|---|---|
| `sequential` (defasagem 0) | as cinco entram numa corrente: a 2ª começa quando a 1ª termina | fila `sequential`, worker único |
| `sequential` (defasagem N) | a 2ª começa N minutos depois da 1ª, a 3ª N depois da 2ª… | fila `sequential`, despacho atrasado |
| `parallel` | as cinco disparam ao mesmo tempo | task workers do Octane |

Tarefas em **grupos diferentes** nunca esperam umas pelas outras — o
enfileiramento é por grupo, não global.

---

# 2. Subindo

```bash
cp .env.example .env          # ajuste MAIL_PASSWORD e as portas se precisar
docker compose up -d --build
```

O container `app` aplica as migrations e cria o usuário do painel na primeira
subida. Depois disso:

| Serviço | Endereço |
|---|---|
| Painel | http://localhost:8095/painel |
| API | http://localhost:8095/api/v1 |
| Octane (interno) | http://localhost:8096 |
| MySQL | `localhost:3310` |
| Redis | `localhost:6382` |

Containers:

```
app                 painel + API (php artisan serve)
octane              servidor Swoole — executa as tarefas paralelas
scheduler           schedule:work — chama tasks:dispatch-due a cada minuto
worker-sequential   queue:work --queue=sequential  (1 processo, de propósito)
worker-parallel     queue:work --queue=parallel    (fallback do Octane; escalável)
mysql, redis
```

> O `worker-sequential` roda **um único processo**. É isso que garante a
> execução uma-após-a-outra. Se escalar esse container, a trava
> `WithoutOverlapping` por grupo ainda impede sobreposição dentro do mesmo
> grupo, mas grupos distintos passam a correr em paralelo entre si (que é o
> comportamento desejado).

---

# 3. Como o agendador funciona

```
   scheduler (a cada minuto)
        │
        ▼
   tasks:dispatch-due
        │
        ├─ lê scheduled_tasks ativas, filtra pelo cron do minuto
        ├─ cria uma linha em task_runs por tarefa (dedupe por minuto)
        │
        ├── modo parallel ──▶ POST octane:8000/octane/dispatch-tasks
        │                     └─ task workers Swoole executam simultaneamente
        │                     └─ Octane fora do ar? cai para a fila "parallel"
        │
        └── modo sequential ─▶ Bus::chain por grupo, fila "sequential"
                              └─ defasagem > 0 quebra a corrente em blocos
                                 despachados com atraso acumulado
```

## Defasagem, na prática

Grupo `loja-123`, todas com cron `0 * * * *`:

| Ordem | Tarefa | Defasagem | Quando começa |
|---|---|---|---|
| 1 | Exportar pedidos | 0 | 09:00 |
| 2 | Sincronizar estoque | 0 | quando a #1 terminar |
| 3 | Recalcular preços | 5 | 09:05 |
| 4 | Enviar relatório | 0 | quando a #3 terminar |
| 5 | Limpar cache | 10 | 09:15 |

Defasagem `0` significa *encadeado*: começa no fim da anterior, não importa
quanto ela demore. Defasagem `N` significa *relógio*: N minutos depois do início
da anterior.

## Por que Octane para o modo paralelo

O `SwooleHttpTaskDispatcher` do Octane aceita despacho vindo de fora do servidor
— o container `scheduler` posta as closures serializadas em
`octane:8000/octane/dispatch-tasks` e os task workers executam concorrentemente,
sem passar por fila e sem o agendador esperar por elas.

Se o Octane não responder, o despacho cai automaticamente para a fila
`parallel` (`SCHEDULER_PARALLEL_DRIVER=queue` força esse caminho). Nada é
perdido; o log registra o fallback.

---

# 4. Painel

`http://localhost:8095/painel` — **login por OTP**, sem senha.

1. Informe o e-mail (apenas os de `PANEL_ALLOWED_EMAILS`, por padrão
   `suporte@vowt.com.br`).
2. Um código de 6 dígitos chega por e-mail, válido por 10 minutos.
3. Código correto → sessão de 8 horas.

Proteções: código guardado apenas como hash, uso único, limite de tentativas,
intervalo mínimo entre reenvios, rate limit por e-mail+IP, e resposta idêntica
para e-mail autorizado ou não (não vaza quais endereços existem).

Telas: visão geral, tarefas (CRUD, pausar, executar agora), execuções (com saída
e erro completos) e clientes da API.

Para liberar outro e-mail:

```bash
docker compose exec app php artisan panel:user novo@vowt.com.br --name="Fulano"
# e acrescente o endereço em PANEL_ALLOWED_EMAILS
```

---

# 5. API

## Autenticação

Todo endpoint sob `/api/v1` exige três cabeçalhos:

```http
X-Client-Id: cid_...        # cadastrado no painel
X-Client-Secret: sk_...     # exibido uma única vez, na criação
X-Store-Uuid: <uuid>        # NÃO é cadastrado — identifica a loja em cada chamada
```

`Authorization: Basic base64(client_id:client_secret)` também é aceito no lugar
dos dois primeiros.

O `storeUuid` delimita tudo: listagens só trazem tarefas daquele store, e
tarefas de outro store respondem `404`. Um cliente pode ser restringido a uma
lista fixa de stores no painel.

## Endpoints

| Método | Rota | O quê |
|---|---|---|
| `GET` | `/api/v1/tasks` | lista as tarefas do store |
| `POST` | `/api/v1/tasks` | cadastra |
| `GET` | `/api/v1/tasks/{uuid}` | detalha |
| `PUT/PATCH` | `/api/v1/tasks/{uuid}` | atualiza |
| `DELETE` | `/api/v1/tasks/{uuid}` | remove |
| `POST` | `/api/v1/tasks/{uuid}/run` | dispara agora (`202`) |
| `GET` | `/api/v1/tasks/{uuid}/runs` | histórico da tarefa |
| `GET` | `/api/v1/runs` | execuções do store |
| `GET` | `/api/v1/runs/{uuid}` | detalhe da execução (`?with_output=1`) |
| `GET` | `/api/health` | health check (público) |

Filtros em `GET /tasks`: `is_active`, `execution_mode`, `execution_group`,
`type`, `search`, `per_page`.

## Cadastrando uma tarefa

```bash
curl -X POST http://localhost:8095/api/v1/tasks \
  -H "X-Client-Id: cid_..." \
  -H "X-Client-Secret: sk_..." \
  -H "X-Store-Uuid: 3f1a8c2e-77b1-4a1e-9a0d-5b6c7d8e9f00" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sincronizar pedidos",
    "type": "http",
    "url": "https://api.loja.com.br/jobs/sync",
    "method": "POST",
    "headers": { "Authorization": "Bearer xyz" },
    "body": { "desde": "ultima-hora" },
    "cron_expression": "0 * * * *",
    "timezone": "America/Sao_Paulo",
    "execution_mode": "sequential",
    "execution_group": "loja-123",
    "sequence_order": 2,
    "stagger_minutes": 5,
    "timeout": 120,
    "max_attempts": 3,
    "retry_delay_seconds": 60
  }'
```

Tarefa de comando:

```json
{
  "name": "Reprocessar fila",
  "type": "command",
  "command": "artisan pedidos:reprocessar --loja=123",
  "cron_expression": "*/15 * * * *",
  "execution_mode": "parallel"
}
```

## Campos

| Campo | Obrigatório | Observação |
|---|---|---|
| `name` | sim | |
| `type` | sim | `http` ou `command` |
| `url`, `method`, `headers`, `body`, `expected_status`, `verify_ssl` | `url` sim (tipo http) | `expected_status` vazio = qualquer 2xx |
| `command` | sim (tipo command) | sujeito à allowlist |
| `cron_expression` | sim | 5 campos padrão |
| `timezone` | não | padrão `America/Sao_Paulo` |
| `execution_mode` | sim | `parallel` ou `sequential` |
| `execution_group` | não | padrão `default`; só vale no modo sequencial |
| `sequence_order` | não | menor roda primeiro dentro do grupo |
| `stagger_minutes` | não | `0` = encadeado; `N` = N min após a anterior |
| `timeout` | não | segundos, teto em `SCHEDULER_MAX_TIMEOUT` |
| `max_attempts` / `retry_delay_seconds` | não | cada tentativa vira uma linha no histórico |
| `is_active` | não | padrão `true` |

Respostas de erro: `401` credenciais, `403` cliente inativo ou store não
permitido, `404` tarefa de outro store, `422` validação.

---

# 6. Tipos de tarefa

## `http`

Dispara a URL com o método, headers e corpo configurados. Sucesso = status na
faixa 2xx, ou exatamente um dos `expected_status` quando informado. Redirecionamentos
não são seguidos (um `302` inesperado é falha, e isso é proposital).

## `command`

Executa um comando dentro do container. Prefixe com `artisan` para comandos do
próprio serviço (`artisan fila:processar` vira `php artisan fila:processar`).

A **allowlist** (`SCHEDULER_COMMAND_ALLOWLIST`) limita quais binários podem ser
invocados — o cadastro é bloqueado na validação e uma segunda checagem acontece
na execução, para pegar tarefas cadastradas antes de a lista mudar. Desligue
(`SCHEDULER_COMMAND_ALLOWLIST_ENABLED=false`) apenas se a API não estiver
exposta a terceiros.

---

# 7. Comandos

```bash
docker compose exec app php artisan tasks:dispatch-due            # ciclo manual
docker compose exec app php artisan tasks:dispatch-due --dry-run  # o que venceria agora
docker compose exec app php artisan tasks:dispatch-due --at="2026-03-10 09:00"
docker compose exec app php artisan tasks:run <uuid>              # despacha
docker compose exec app php artisan tasks:run <uuid> --sync       # roda na hora e mostra a saída
docker compose exec app php artisan tasks:prune-runs --days=15
docker compose exec app php artisan panel:user email@vowt.com.br
```

---

# 8. Modelo de dados

| Tabela | Papel |
|---|---|
| `scheduled_tasks` | cadastro: o que, quando, como, de quem (`store_uuid`) |
| `task_runs` | uma linha por execução: status, duração, saída, erro, tentativa |
| `api_clients` | credenciais da API (segredo só como hash) |
| `panel_users` | quem acessa o painel |
| `otp_codes` | códigos de login (hash, expiração, tentativas) |

`task_runs.dedupe_key` (`sched:<task>:<AAAAMMDDHHMM>`) impede que o mesmo minuto
seja despachado duas vezes se o agendador reiniciar.

---

# 9. Testes

```bash
docker compose exec app php artisan test
```

Rodam contra o banco `schedule_service_test`, criado pelo
`docker/mysql/init.sql`. Cobrem autenticação da API, escopo por store, CRUD,
validação de cron e de allowlist, login OTP (expiração, uso único, reenvio),
execução HTTP e de comando, retentativas, e o núcleo do agendador — corrente
sequencial, ordem, defasagem acumulada, isolamento entre grupos, despacho
paralelo e o fallback para fila quando o Octane não responde.

---

# 10. Variáveis de ambiente

| Variável | Padrão | Para quê |
|---|---|---|
| `SCHEDULER_PARALLEL_DRIVER` | `octane` | `octane` ou `queue` |
| `SCHEDULER_OCTANE_HOST` / `_PORT` | `octane` / `8000` | onde o Swoole atende |
| `SCHEDULER_PARALLEL_CHUNK` | `10` | tarefas por chamada ao Octane |
| `SCHEDULER_SEQUENTIAL_QUEUE` | `sequential` | fila das correntes |
| `SCHEDULER_DEFAULT_GROUP` | `default` | grupo de quem não informa |
| `SCHEDULER_LOCK_SECONDS` | `3600` | validade da trava por grupo |
| `SCHEDULER_DEFAULT_TIMEOUT` | `60` | timeout padrão (s) |
| `SCHEDULER_MAX_TIMEOUT` | `900` | teto aceito no cadastro |
| `SCHEDULER_RUN_RETENTION_DAYS` | `30` | retenção do histórico |
| `SCHEDULER_COMMAND_ALLOWLIST_ENABLED` | `true` | liga a allowlist |
| `SCHEDULER_COMMAND_ALLOWLIST` | `php,curl,echo,ls,artisan` | binários liberados |
| `PANEL_ALLOWED_EMAILS` | `suporte@vowt.com.br` | quem pode pedir OTP |
| `OTP_TTL_MINUTES` / `OTP_MAX_ATTEMPTS` / `OTP_RESEND_SECONDS` | `10` / `5` / `60` | política do código |
| `OCTANE_WORKERS` / `OCTANE_TASK_WORKERS` | `4` / `16` | concorrência do Octane |

O SMTP de envio do OTP fica em `MAIL_*`. `MAIL_PASSWORD` está no `.env` (que é
ignorado pelo git) e vazio no `.env.example` — não versione a senha.

---

# 11. Notas de operação

- **Escalar o paralelo:** aumente `OCTANE_TASK_WORKERS`. No fallback de fila,
  `docker compose up -d --scale worker-parallel=4`.
- **Nunca escale o `worker-sequential` esperando mais vazão:** a serialização é
  o recurso. Para ganhar paralelismo, separe as tarefas em mais grupos.
- **Tarefa longa:** ajuste `timeout` na tarefa e mantenha o `--timeout` do
  `queue:work` acima dele (hoje 960 s contra o teto de 900 s).
- **Divergência com o resto da plataforma:** os demais microserviços usam
  PostgreSQL; este usa MySQL, conforme pedido. O padrão *database per service* é
  respeitado — nenhum outro serviço toca este banco.

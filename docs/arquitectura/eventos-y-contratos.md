## Eventos y contratos (MyBB → mecánicas)

### Principio

MyBB **nunca** calcula reglas complejas de rol. Solo:
- Detecta “algo pasó” (hook).
- Empaqueta un evento (envelope estándar).
- Envía el evento a la capa de mecánicas.

### Envelope recomendado

- `event_id`: UUID
- `event_type`: string (p.ej. `post_created`)
- `occurred_at`: ISO-8601
- `source`: `mybb`
- `actor`: `{ uid, username }`
- `context`: `{ thread_id, post_id, forum_id }` (según evento)
- `payload`: objeto específico del evento
- `version`: número/semver del contrato

### Contratos

Los contratos viven en `packages/contracts/`:
- OpenAPI para endpoints
- JSON Schema para validar payloads


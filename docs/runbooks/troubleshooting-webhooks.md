> **Solo aplica si se implementa `rpg_bridge`.** En D001 (MySQL local + plugin) no hay webhooks de mecánicas.

## Troubleshooting webhooks

### Síntomas típicos

- **Timeout**: MyBB tarda demasiado en recibir respuesta.
- **401/403**: token inválido o firma incorrecta.
- **422**: payload no cumple contrato (schema).
- **500**: error interno en la función/RPC.

### Qué mirar

- Logs del backend (Edge Function) con `event_id`.
- Latencia de DB (RPC).
- Reintentos en MyBB (si implementas retry).


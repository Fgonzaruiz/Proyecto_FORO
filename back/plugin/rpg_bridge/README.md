## `rpg_bridge` — Plugin puente (MyBB hooks → Webhooks)

Responsabilidad:
- Escuchar hooks oficiales de MyBB
- Construir un **evento** (envelope)
- Enviar HTTP webhook al backend de mecánicas
- Recibir JSON y aplicar efectos “ligeros” en MyBB (mensajes, flags, etc.)

Código “pesado” (exp/daño/economía) debe vivir fuera del plugin.


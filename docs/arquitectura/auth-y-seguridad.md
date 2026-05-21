## Auth y seguridad

### Requisitos mínimos

- El backend de mecánicas debe exigir `Authorization: Bearer <token>`.
- El token se configura como variable de entorno/secret en el servidor MyBB y en el backend.

### Recomendación adicional (anti-replay)

Añadir firma HMAC del cuerpo:
- Headers: `X-Timestamp`, `X-Signature`
- Firma: HMAC-SHA256(`timestamp + "." + rawBody`, secret)
- Ventana máxima: 3–5 minutos

### Principios

- No confiar en inputs del cliente.
- Validar esquema de evento (JSON schema) en el backend.
- Registrar auditoría de eventos (ids, actor, outcome).


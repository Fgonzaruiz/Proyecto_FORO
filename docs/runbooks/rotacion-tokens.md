## Rotación de tokens (MyBB ↔ backend mecánicas)

Checklist:
- Generar nuevo token fuerte.
- Configurar en backend (secret/env).
- Configurar en servidor MyBB (env/secret).
- Mantener ventana de solape (opcional) si el backend soporta dos tokens.
- Revocar token viejo.
- Revisar logs de auth failures tras la rotación.


## `tools/` — scripts y utilidades

Aquí van herramientas de desarrollo (no runtime):
- sync de plantillas a XML de tema
- split/merge de plantillas grandes (límite MyBB)
- validación de payloads contra JSON Schema
- generación de tipos a partir de contratos

### Super guías PREMIUM (para LLM / cambios grandes)

| Script | Salida | Cuándo |
|--------|--------|--------|
| `python tools/audit_frontend_metrics.py` | `docs/auditoria-frontend-foro.html` + `docs/auditoria-metrics.json` | Tras cambios CSS/plantillas/JS |
| `python tools/audit_backend_contracts.py` | `docs/auditoria-backend-foro.html` + `docs/auditoria-backend-metrics.json` | Tras cambios PHP/SQL/AJAX |
| `python tools/generate_audit_super_html.py` | Ambos HTML (sin correr gates) | Solo refrescar documentación |

Reglas editables en `tools/audit_premium_catalog.py`.

## Foro RPG híbrido (MyBB + backend de mecánicas)

Este repositorio contiene la **estructura base** para un foro de rol “play-by-post” montado sobre **MyBB** (runtime PHP/MySQL)
y un backend de mecánicas integrado en el módulo `game/` (MySQL local vía MyBB $db).

La idea es replicar el patrón del documento “Nakama”:
- `back/` es lo que **se despliega** (docroot ejecutable).
- `front/` es donde se **autoría** el tema/plantillas sin tocar el runtime.
- `docs/` define arquitectura, eventos/contratos y seguridad.

### Estructura

- `back/`: instalación MyBB + plugin puente + módulo `game/` (si decides mantener páginas custom en PHP).
- `front/`: fuentes del tema y plantillas (export/import).
- `packages/contracts/`: contratos (OpenAPI/JSON schema) entre MyBB y backend de mecánicas.
- `back/forum/game/`: backend de mecánicas (MySQL local vía MyBB $db).
- `tools/`: scripts de soporte (sync plantillas, validación payloads, etc.).

### Primeros pasos (orientativo)

1. Copia una instalación limpia de MyBB dentro de `back/forum/` (no incluida en el repo por licencia/tamaño).
2. Implementa el plugin MyBB en `back/forum/inc/plugins/rpg_bridge/` (o empaqueta desde `back/plugin/` según tu flujo).
3. Define contratos de eventos en `packages/contracts/` (envelope + payloads).
4. Implementa endpoints de mecánicas en `back/forum/game/ajax/` (o tu backend alternativo).


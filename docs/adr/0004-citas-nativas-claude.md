# ADR 0004 — Citas nativas de Claude con bloques `search_result`

**Estado:** aceptada · 2026-11-24

## Contexto
Pedirle al modelo que escriba "[n]" en texto libre produce citas mal numeradas o inventadas, y no indica qué pasaje exacto respalda cada afirmación.

## Decisión
Enviar cada fragmento recuperado como bloque `search_result` con `citations: {enabled: true}` y `source` = id del fragmento. La API devuelve citas `search_result_location` con el texto citado literalmente; `AnswerComposer` las convierte en referencias numeradas `[n]` con sus citas textuales.

Además:
- El prompt de sistema obliga a responder solo con la evidencia, admitir cuando no alcanza, y trata los resultados como datos (no como instrucciones) para mitigar *prompt injection* desde documentos.
- Si la búsqueda no devuelve evidencia, **no se llama al LLM** y se responde explícitamente que no hay información.
- `fallbacks: "default"` reintenta en el servidor con otro modelo si el principal declina; una negativa definitiva se traduce en `AnswerRefused` (HTTP 422).
- El esfuerzo (`effort`) se fija explícitamente y es configurable.

## Consecuencias
- Cada cita es verificable: apunta a un `chunk_id`, documento y página.
- Existe un generador extractivo sin LLM (`RAG_GENERATOR=extractive`) para demos y CI, que respeta el mismo contrato de citas.

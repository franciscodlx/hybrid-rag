# ADR 0001 — Arquitectura hexagonal con el núcleo fuera de `app/`

**Estado:** aceptada · 2026-11-24

## Contexto
Un RAG combina piezas que cambian a ritmos distintos: proveedores de embeddings y LLM, motores de búsqueda y formatos de documento. Acoplar la lógica de recuperación a Laravel o a un proveedor haría caro cambiar cualquiera de ellos y difícil probar el núcleo.

## Decisión
- `src/Domain`: reglas puras (BM25, RRF, fragmentación, citas). Sin framework.
- `src/Application`: casos de uso (`IngestDocumentHandler`, `HybridSearcher`, `AskQuestionHandler`) que orquestan puertos.
- `src/Infrastructure`: adaptadores intercambiables.
- `app/`: solo entrega (HTTP y consola) y el *composition root* (`RagServiceProvider`).

Las reglas de dependencia se verifican con tests de arquitectura de Pest (`tests/Architecture`).

## Consecuencias
- El dominio se prueba en milisegundos, sin base de datos ni red.
- Cambiar Voyage por Ollama o pgvector por otro motor es implementar una interfaz y cambiar una variable de entorno.
- Hay más archivos que en un CRUD de Laravel típico; es el costo aceptado.

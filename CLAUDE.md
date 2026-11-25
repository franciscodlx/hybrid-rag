# CLAUDE.md — hybrid-rag

RAG híbrido: BM25 + búsqueda vectorial fusionadas con RRF y respuestas citadas (Claude) sobre documentos privados. Laravel 13 / PHP 8.4. Proyecto personal de portafolio, no es parte de Helm.

## Comandos

```sh
composer qa              # pint --test + phpstan (nivel 8) + pest — debe quedar en verde
./vendor/bin/pest        # tests (los de pgvector se omiten sin RAG_PGVECTOR_TEST_HOST)
./vendor/bin/pint        # formateo
docker compose up -d     # PostgreSQL 17 + pgvector
RAG_PGVECTOR_TEST_HOST=127.0.0.1 ./vendor/bin/pest tests/Integration/PgVectorIndexTest.php
```

## Arquitectura (hexagonal) — reglas que los tests de `tests/Architecture` hacen cumplir

- `src/Domain` (`HybridRag\Domain`): puro. Solo puede usar `HybridRag\Domain` y `Ramsey\Uuid`. Clases `final readonly`.
- `src/Application` (`HybridRag\Application`): casos de uso; solo Domain + `Psr\Clock`.
- `src/Infrastructure`: adaptadores (Postgres/SQLite, pgvector, Voyage, Ollama, Claude, PDF). Nunca depende de `App\`.
- `app/` (`App\`): solo entrega (HTTP, consola) y `RagServiceProvider`, el único composition root.

Al añadir un adaptador: implementar el puerto, registrarlo en `RagServiceProvider`, y si es un índice, añadirlo al test de contrato correspondiente.

## Invariantes que no hay que romper

- BM25 canónico en `Domain/Retrieval/Bm25.php`; `SqlBm25Index` replica la fórmula en SQL y `tests/Integration/LexicalIndexContractTest.php` exige igualdad con la versión en memoria.
- Mismo `Analyzer` para indexar y consultar.
- Toda búsqueda y lectura se filtra por `CollectionId` (aislamiento entre colecciones).
- Embeddings fuera de la transacción; documento + índices dentro de ella.
- `rag.embeddings.dimensions` debe coincidir con la columna `vector(N)`; cambiarlo exige migrar y re-indexar.
- Claude: bloques `search_result` con `citations.enabled`, `fallbacks: "default"` (beta `server-side-fallback-2026-07-01`), esfuerzo explícito. Las citas se mapean por `source` = id del fragmento.

## Convenciones

Prosa y mensajes en español; identificadores en inglés. `declare(strict_types=1)` en todo archivo. No subir `.env`.

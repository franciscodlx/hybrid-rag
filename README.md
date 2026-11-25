# Hybrid RAG

**Búsqueda BM25 + vectorial fusionada con RRF y respuestas citadas sobre documentos privados.**

`RAG` · `BM25` · `Vector search` · `RRF` · `pgvector` · `Claude` · `Laravel 13` · `PHP 8.4`

---

## ¿Por qué híbrido?

Ningún recuperador por sí solo cubre todas las preguntas reales:

| Pregunta | BM25 (léxico) | Vectorial (semántico) |
|---|---|---|
| «¿Qué significa **ERR-4521**?» | ✅ coincidencia exacta del código | ❌ el embedding diluye el identificador |
| «¿Cuántos días libres me dan al año?» (el documento dice *vacaciones*) | ❌ no comparte términos | ✅ entiende la paráfrasis |

Este proyecto ejecuta **ambos** en paralelo y fusiona los rankings con **Reciprocal Rank Fusion**. Después, Claude redacta la respuesta usando **solo** los fragmentos recuperados y devuelve **citas nativas** que apuntan al pasaje exacto de cada documento.

```mermaid
flowchart LR
    Q[Pregunta] --> A[Analyzer]
    Q --> E[Embedder<br/>input_type=query]
    A --> L[BM25<br/>índice invertido SQL]
    E --> V[k-NN coseno<br/>pgvector HNSW]
    L -->|ranking léxico| F[RRF<br/>Σ w/(k+rank)]
    V -->|ranking semántico| F
    F --> H[Top-k fragmentos]
    H -->|sin evidencia| N[«No hay información»<br/>sin llamar al LLM]
    H -->|search_result + citations| C[Claude]
    C --> R[Respuesta con citas [n]<br/>+ pasajes literales]
```

## Características

- **BM25 real**, no `ts_rank`: Okapi BM25 (IDF de Lucene) calculado en SQL portable sobre un índice invertido propio. Un test de contrato garantiza que da **las mismas puntuaciones** que la implementación de referencia.
- **Búsqueda vectorial** con pgvector + HNSW (coseno) e *iterative scan* para filtrar por colección sin perder resultados.
- **Fusión RRF** con pesos por recuperador y **explicabilidad**: cada resultado indica en qué posición lo puso BM25 y en cuál el vectorial.
- **Respuestas citadas** con bloques `search_result` de Claude: cada cita lleva documento, página y el texto citado literalmente.
- **Honestidad por diseño:** si no hay evidencia no se llama al LLM; el prompt prohíbe usar conocimiento externo y trata el contenido de los documentos como datos, no como instrucciones (mitigación de *prompt injection*).
- **Documentos privados:** API con token *fail-closed*, aislamiento estricto por colección y opción de embeddings 100 % locales con Ollama.
- **Ingesta idempotente** por checksum (no se paga dos veces por los mismos embeddings), fragmentación recursiva con solape que respeta párrafos y páginas, y soporte de TXT, Markdown, CSV y PDF.
- **Proveedores intercambiables**: Voyage AI / Ollama / hashing offline para embeddings, y Claude / extractivo sin LLM para generación.

## Arquitectura

Hexagonal ([ADR 0001](docs/adr/0001-arquitectura-hexagonal.md)). El núcleo no conoce Laravel ni los proveedores, y las reglas de dependencia se verifican con tests de arquitectura.

```
src/
├── Domain/                    # Reglas puras, sin framework
│   ├── Document/              # Document, Chunk, CollectionId, DocumentRepository (puerto)
│   ├── Text/                  # StandardAnalyzer, RecursiveChunker
│   ├── Retrieval/             # Bm25, RankedList, LexicalIndex, VectorIndex (puertos)
│   │   └── Fusion/            # ReciprocalRankFusion
│   ├── Embedding/             # Embedding, Embedder (puerto)
│   └── Answering/             # Answer, Citation, AnswerComposer, AnswerGenerator (puerto)
├── Application/               # Casos de uso
│   ├── Ingestion/             # IngestDocumentHandler, TextExtractor (puerto)
│   ├── Search/                # HybridSearcher
│   ├── Answering/             # AskQuestionHandler
│   └── Documents/             # DeleteDocumentHandler
└── Infrastructure/            # Adaptadores
    ├── Persistence/Database/  # Repositorio, SqlBm25Index, PgVectorIndex
    ├── Persistence/InMemory/  # Implementaciones de referencia (tests)
    ├── Embedding/             # Voyage, Ollama, Hashing
    ├── Generation/            # ClaudeAnswerGenerator, ExtractiveAnswerGenerator
    └── Extraction/            # Texto plano, PDF por página
app/                           # Solo entrega: HTTP, consola y RagServiceProvider (composition root)
```

| Capa | Puede depender de |
|---|---|
| `Domain` | nada (solo `ramsey/uuid`) |
| `Application` | `Domain`, `psr/clock` |
| `Infrastructure` | `Domain`, `Application`, librerías |
| `app/` | todo |

Decisiones documentadas en [`docs/adr`](docs/adr):
[hexagonal](docs/adr/0001-arquitectura-hexagonal.md) ·
[BM25 en SQL](docs/adr/0002-bm25-en-sql.md) ·
[RRF](docs/adr/0003-fusion-rrf.md) ·
[citas nativas](docs/adr/0004-citas-nativas-claude.md).

## Puesta en marcha

Requisitos: PHP 8.4, Composer y Docker.

```sh
docker compose up -d                 # PostgreSQL 17 + pgvector
composer install
cp .env.example .env && php artisan key:generate
# En .env: RAG_API_TOKEN, VOYAGE_API_KEY y ANTHROPIC_API_KEY
php artisan migrate
php artisan serve
```

**Sin claves de API** (demo offline): `RAG_EMBEDDINGS=hashing`, `RAG_EMBEDDING_DIMENSIONS=256` y `RAG_GENERATOR=extractive`. El embedder por hashing **no es semántico**; sirve para probar el flujo, no para medir calidad.

**Privacidad total** (nada sale de la máquina para los embeddings): `RAG_EMBEDDINGS=ollama`, `RAG_EMBEDDING_DIMENSIONS=768` y `ollama pull nomic-embed-text`. Ten en cuenta que, con `RAG_GENERATOR=claude`, los fragmentos recuperados sí se envían a la API de Anthropic para redactar la respuesta.

> `RAG_EMBEDDING_DIMENSIONS` define la columna `vector(N)`. Si cambias de modelo de embeddings, ejecuta `php artisan migrate:fresh` y vuelve a indexar.

## Uso

### Consola

```sh
php artisan rag:ingest docs/samples --collection=demo
php artisan rag:search "ERR-4521" --collection=demo
php artisan rag:ask "¿Cuántos días de vacaciones tengo y cómo los solicito?" --collection=demo
```

`rag:search` muestra la puntuación RRF junto con el rango que le dio cada recuperador:

```
| # | RRF    | BM25 | Vector | Documento          | Fragmento                                         |
| 1 | 0.0328 | 1    | 1      | runbook-incidentes | ## ERR-4521: certificado TLS expirado …           |
| 2 | 0.0320 | 2    | 3      | runbook-incidentes | El error ERR-4521 aparece cuando el certificado … |
```

### API REST

Todas las rutas requieren `Authorization: Bearer $RAG_API_TOKEN`.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/v1/collections/{c}/documents` | Lista documentos |
| `POST` | `/api/v1/collections/{c}/documents` | Sube `file` (+ `title` opcional). `201` nuevo · `200` ya existía |
| `GET` | `/api/v1/collections/{c}/documents/{id}` | Detalle |
| `DELETE` | `/api/v1/collections/{c}/documents/{id}` | Borra documento, fragmentos e índices |
| `POST` | `/api/v1/collections/{c}/search` | `{query, limit?}` → fragmentos fusionados |
| `POST` | `/api/v1/collections/{c}/ask` | `{question, evidence_limit?}` → respuesta citada |

```sh
curl -H "Authorization: Bearer $TOKEN" -F file=@docs/samples/runbook-incidentes.md \
     http://localhost:8000/api/v1/collections/demo/documents

curl -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
     -d '{"question":"¿Cómo resuelvo el ERR-4521?"}' \
     http://localhost:8000/api/v1/collections/demo/ask
```

```json
{
  "data": {
    "answer": "Renueva el certificado TLS del balanceador y reinicia el listener HTTPS. [1]",
    "grounded": true,
    "generator": "claude:claude-opus-5-5",
    "citations": [{
      "number": 1,
      "chunk_id": "0193…",
      "document_title": "runbook-incidentes",
      "page": null,
      "quotes": ["renueve el certificado con el gestor de certificados y reinicie el listener HTTPS"]
    }],
    "evidence": [{ "chunk_id": "0193…", "score": 0.0328, "ranks": { "bm25": 1, "vector": 1 }, "…": "…" }]
  }
}
```

Errores: `401` sin token · `404` documento inexistente o de otra colección · `415` formato no soportado · `422` validación o negativa del modelo · `429` límite de peticiones (`ask`: 30/min) · `502` fallo de un proveedor externo, sin exponer detalles internos.

## Configuración

| Variable | Por defecto | Descripción |
|---|---|---|
| `RAG_API_TOKEN` | — | Token Bearer. Vacío ⇒ API cerrada |
| `RAG_STORE` / `RAG_VECTOR_STORE` | `database` / `pgvector` | `memory` solo para tests |
| `RAG_EMBEDDINGS` | `voyage` | `voyage` · `ollama` · `hashing` |
| `RAG_EMBEDDING_DIMENSIONS` | `1024` | Debe coincidir con el modelo |
| `RAG_GENERATOR` | `claude` | `claude` · `extractive` |
| `RAG_CLAUDE_MODEL` / `RAG_CLAUDE_EFFORT` | `claude-opus-5-5` / `medium` | Modelo y esfuerzo de razonamiento |
| `RAG_CLAUDE_FALLBACK` | `true` | Reintento en servidor con otro modelo si el principal declina |
| `RAG_CHUNK_MAX_CHARS` / `RAG_CHUNK_OVERLAP_CHARS` | `1200` / `150` | Fragmentación |
| `RAG_CANDIDATES` | `40` | Candidatos por recuperador antes de fusionar |
| `RAG_RRF_K` | `60` | Constante de RRF |
| `RAG_WEIGHT_BM25` / `RAG_WEIGHT_VECTOR` | `1.0` / `1.0` | Peso de cada recuperador |
| `RAG_BM25_K1` / `RAG_BM25_B` | `1.2` / `0.75` | Parámetros de BM25 |

## Calidad

```sh
composer qa      # Pint + PHPStan (nivel 8) + Pest
```

- **Unitarios** (dominio y casos de uso, sin base de datos ni red): BM25, RRF, chunker, analizador, citas, ingesta idempotente, aislamiento por colección.
- **Contrato**: `SqlBm25Index` frente a la implementación de referencia en memoria (mismo ranking y puntuaciones ±1e-9); repositorio en memoria frente al de base de datos.
- **Adaptadores**: el SDK real de Anthropic con un transporte HTTP simulado (payload `search_result`, mapeo de citas, negativas) y Voyage con `Http::fake`.
- **HTTP de extremo a extremo**: autenticación, idempotencia, validación, búsqueda, respuestas citadas y borrado.
- **Arquitectura**: reglas de dependencia entre capas, `strict_types`, inmutabilidad del dominio y presets de seguridad.
- **pgvector** (requiere Docker): `RAG_PGVECTOR_TEST_HOST=127.0.0.1 ./vendor/bin/pest tests/Integration/PgVectorIndexTest.php`

## Hoja de ruta

- Ingesta asíncrona con colas y estado del documento para archivos grandes.
- Autorización por colección (tokens con alcance) para un escenario multi-tenant.
- *Reranker* (cross-encoder) después de RRF.
- Conjunto de evaluación (recall@k, MRR, fidelidad de citas) para ajustar pesos y `k`.
- Respuestas en streaming (SSE).
- OCR para PDF escaneados, y soporte DOCX/HTML (un `TextExtractor` más cada uno).
- `LexicalIndex` sobre ParadeDB u OpenSearch para corpus muy grandes, validado con el mismo test de contrato.

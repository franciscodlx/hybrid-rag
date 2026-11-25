# ADR 0002 — BM25 real en SQL sobre un índice invertido propio

**Estado:** aceptada · 2026-11-24

## Contexto
`ts_rank` de PostgreSQL no es BM25: no satura la frecuencia de términos ni normaliza por longitud de documento como BM25. Las opciones con BM25 real eran ParadeDB (`pg_search`), OpenSearch/Elasticsearch, o calcularlo nosotros.

## Decisión
Mantener un índice invertido (`rag_lexical_docs`, `rag_lexical_postings`) y calcular Okapi BM25 (IDF de Lucene) en una sola consulta SQL portable. La fórmula canónica vive en `Domain\Retrieval\Bm25`; un test de contrato exige que el SQL dé **las mismas puntuaciones** (±1e-9) que la implementación en memoria.

## Consecuencias
- Una sola base de datos (PostgreSQL + pgvector), sin servicios extra ni extensiones exóticas. También corre en SQLite para tests.
- Las estadísticas (N, avgdl, df) se calculan por consulta y por colección: correcto siempre, sin reindexar; adecuado para corpus de decenas o cientos de miles de fragmentos.
- A gran escala conviene ParadeDB u OpenSearch: basta con otra implementación de `LexicalIndex`, validada por el mismo test de contrato.

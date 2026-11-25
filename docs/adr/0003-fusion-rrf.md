# ADR 0003 — Fusión con Reciprocal Rank Fusion

**Estado:** aceptada · 2026-11-24

## Contexto
BM25 devuelve puntuaciones no acotadas y la similitud coseno está en [-1, 1]. Combinarlas linealmente exige normalizar y calibrar pesos por corpus, lo que es frágil.

## Decisión
Usar RRF (Cormack et al., 2009): `score(d) = Σ w_r / (k + rank_r(d))`, con `k = 60` y pesos configurables por recuperador. Cada recuperador aporta un pool amplio (`RAG_CANDIDATES`, 40 por defecto) antes de fusionar.

## Consecuencias
- Robusto sin calibración: solo importan las posiciones.
- Premia el consenso: un fragmento que ambos recuperadores ponen en la posición 2 supera a uno que solo uno pone en la posición 1.
- La respuesta de la API expone el rango de cada recuperador (`ranks`), lo que hace explicable y depurable el resultado.
- Un *reranker* (cross-encoder) después de RRF es el siguiente paso natural si se necesita más precisión.

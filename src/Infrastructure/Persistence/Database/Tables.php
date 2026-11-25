<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

/**
 * Nombres de tabla centralizados: cada adaptador es dueño de las suyas.
 */
final class Tables
{
    public const string DOCUMENTS = 'rag_documents';

    public const string CHUNKS = 'rag_chunks';

    public const string LEXICAL_DOCS = 'rag_lexical_docs';

    public const string LEXICAL_POSTINGS = 'rag_lexical_postings';

    public const string EMBEDDINGS = 'rag_embeddings';

    private function __construct() {}
}

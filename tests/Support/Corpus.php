<?php

declare(strict_types=1);

namespace Tests\Support;

use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;

/**
 * Corpus pequeño pero con las situaciones que justifican un RAG híbrido:
 * códigos exactos (BM25 gana), paráfrasis (vector gana) y ruido.
 */
final class Corpus
{
    public const array TEXTS = [
        'La política de vacaciones otorga 20 días hábiles al año a cada colaborador.',
        'El error ERR-4521 aparece cuando el certificado TLS del servidor ha expirado.',
        'Para solicitar días libres, el empleado debe registrar la ausencia en el portal de RR. HH.',
        'Los reembolsos de viáticos se procesan en un máximo de 10 días tras aprobar la factura.',
        'El servidor de producción usa PostgreSQL 17 con la extensión pgvector para búsquedas.',
        'La política de seguridad exige rotar contraseñas cada 90 días y usar MFA.',
    ];

    /**
     * @return list<Chunk>
     */
    public static function chunks(string $collection = 'acme', ?DocumentId $documentId = null): array
    {
        $documentId ??= DocumentId::generate();

        return array_map(
            static fn (string $text, int $i): Chunk => new Chunk(
                ChunkId::generate(),
                $documentId,
                CollectionId::fromString($collection),
                $i,
                $text,
            ),
            self::TEXTS,
            array_keys(self::TEXTS),
        );
    }
}

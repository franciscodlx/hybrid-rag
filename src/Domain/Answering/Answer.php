<?php

declare(strict_types=1);

namespace HybridRag\Domain\Answering;

final readonly class Answer
{
    /**
     * @param  list<Citation>  $citations
     */
    public function __construct(
        public string $text,
        public array $citations,
        public string $generator,
    ) {}

    /**
     * Sin evidencia no se llama al LLM: se responde de forma honesta en vez
     * de dejar que el modelo improvise con su conocimiento general.
     */
    public static function withoutEvidence(): self
    {
        return new self(
            'No encontré información en los documentos de esta colección para responder la pregunta.',
            [],
            'none',
        );
    }

    public function isGrounded(): bool
    {
        return $this->citations !== [];
    }
}

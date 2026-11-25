<?php

declare(strict_types=1);

namespace HybridRag\Domain\Shared;

/**
 * Base de todas las excepciones de negocio. La capa HTTP las traduce a
 * respuestas 4xx; cualquier otra excepción se considera un fallo técnico.
 */
abstract class DomainException extends \DomainException {}

<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente PSR-18 falso: registra la petición y devuelve una respuesta fija.
 * Permite probar el adaptador real del SDK sin red ni coste.
 */
final class RecordingTransporter implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(private readonly array $body, private readonly int $status = 200) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response($this->status, ['content-type' => 'application/json'], json_encode($this->body, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function lastPayload(): array
    {
        $request = end($this->requests) ?: throw new \LogicException('No hubo peticiones.');

        return json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class FakePsr18Client implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param list<ResponseInterface> $responses
     */
    public function __construct(private array $responses)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('No mock Cloudinary response was configured.');
        }

        return $response;
    }
}

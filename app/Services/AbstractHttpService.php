<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract readonly class AbstractHttpService
{
    abstract protected function apiBaseUrl(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function apiHeaders(): array;

    abstract protected function apiLogChannel(): string;

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws ConnectionException
     */
    protected function get(string $path, array $params = []): Response
    {
        $this->logRequest('GET', $path, $params);

        return Http::timeout(10)->withHeaders($this->apiHeaders())->acceptJson()
            ->get($this->apiBaseUrl().$path, $params);
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws ConnectionException
     */
    protected function post(string $path, array $body = []): Response
    {
        $this->logRequest('POST', $path, $body);

        return Http::timeout(10)->withHeaders($this->apiHeaders())->acceptJson()
            ->post($this->apiBaseUrl().$path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws ConnectionException
     */
    protected function put(string $path, array $body = []): Response
    {
        $this->logRequest('PUT', $path, $body);

        return Http::timeout(10)->withHeaders($this->apiHeaders())->acceptJson()
            ->put($this->apiBaseUrl().$path, $body);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertSuccessful(string $context, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $errors = $response->json('errors');

        $message = match (true) {
            is_array($errors) => implode(', ', array_map(fn (mixed $e): string => is_scalar($e) ? (string) $e : '', $errors)),
            is_string($errors) => $errors,
            default => $response->body(),
        };

        throw new RuntimeException(sprintf('%s: HTTP %d — %s', $context, $response->status(), $message));
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonObject(Response $response, string $key): array
    {
        $data = $response->json($key);

        if (! is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function jsonList(Response $response, string $key): array
    {
        $data = $response->json($key);

        if (! is_array($data)) {
            return [];
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function logRequest(string $method, string $path, array $params = []): void
    {
        Log::channel($this->apiLogChannel())->debug(sprintf('%s %s', $method, $path), $params);
    }
}

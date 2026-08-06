<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

use InvalidArgumentException;
use JsonException;

final class JsonResponse
{
    private const JSON_FLAGS =
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        private array $payload,
        private int $statusCode = 200,
        private array $headers = []
    ) {
        if (
            $statusCode < 100
            || $statusCode > 599
        ) {
            throw new InvalidArgumentException(
                'O código HTTP deve estar entre 100 e 599.'
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(
        array $data,
        int $statusCode = 200
    ): self {
        return new self(
            [
                'success' => true,
                'data' => $data,
            ],
            $statusCode
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function error(
        string $code,
        string $message,
        int $statusCode,
        array $data = [],
        array $headers = []
    ): self {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return new self(
            $payload,
            $statusCode,
            $headers
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode(
            $this->payload,
            self::JSON_FLAGS
        );
    }

    /**
     * @throws JsonException
     */
    public function send(): void
    {
        if (!headers_sent()) {
            header(
                'Content-Type: application/json; charset=utf-8'
            );
            header(
                'X-Content-Type-Options: nosniff'
            );
            header(
                'X-Frame-Options: SAMEORIGIN'
            );
            header(
                'Referrer-Policy: strict-origin-when-cross-origin'
            );

            foreach ($this->headers as $name => $value) {
                header(
                    sprintf(
                        '%s: %s',
                        $name,
                        $value
                    )
                );
            }
        }

        http_response_code(
            $this->statusCode
        );

        echo $this->toJson();
    }
}

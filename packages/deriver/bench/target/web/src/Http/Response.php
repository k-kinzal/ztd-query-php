<?php

declare(strict_types=1);

namespace Harbor\Http;

final readonly class Response
{
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers + ['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'same-origin', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "default-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'"] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}

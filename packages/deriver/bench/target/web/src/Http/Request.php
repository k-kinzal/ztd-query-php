<?php

declare(strict_types=1);

namespace Harbor\Http;

use JsonException;

final readonly class Request
{
    public function __construct(public string $method, public string $path, public array $query = [], public array $body = [], public array $headers = [], public string $ip = '127.0.0.1')
    {
    }

    public static function capture(): self
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 1048576) {
            throw new Problem(413, 'The request body exceeds one megabyte.');
        }
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $body = $_POST;
        if (str_starts_with($headers['content-type'] ?? '', 'application/json')) {
            try {
                $raw = file_get_contents('php://input', false, null, 0, 1048577);
                if ($raw === false || strlen($raw) > 1048576) {
                    throw new Problem(413, 'The request body exceeds one megabyte.');
                }
                $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($body)) {
                    throw new Problem(400, 'A JSON object is required.');
                }
            } catch (JsonException) {
                throw new Problem(400, 'Invalid JSON.');
            }
        }
        return new self($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', $_GET, $body, $headers, $_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function cursor(string $name, int $default): int
    {
        $value = filter_var($this->query[$name] ?? $default, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($value === false) {
            throw new Problem(422, 'Invalid pagination cursor.');
        }
        return $value;
    }
}

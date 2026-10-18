<?php

declare(strict_types=1);

namespace Harbor\Http;

final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    public function render(string $template, array $variables): Response
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            require $this->directory . '/' . $template . '.php';
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return new Response(200, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use Override;
use RuntimeException;

/**
 * Reads a server's own lexer source over the network.
 *
 * Profiles are generated from the exact source of the release they name, so the
 * fetch is part of the record: a URL that cannot be read means the profile
 * cannot be built rather than that it should be built from something else.
 * PHP reports a failed stream as a warning and a false return, so warnings are
 * turned into the failure they describe.
 */
final class UpstreamLexerSource implements LexerSource
{
    /**
     * Reads one upstream file over HTTP.
     *
     * @param string $url Location of the file
     *
     * @return string The file's contents
     *
     * @throws RuntimeException When the file cannot be read
     */
    #[Override]
    public function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'user_agent' => 'sql-faker/1.0',
            ],
        ]);

        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message);
        });
        try {
            $contents = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            throw new RuntimeException("Failed to fetch {$url}");
        }

        return $contents;
    }
}

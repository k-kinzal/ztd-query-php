<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use RuntimeException;

/**
 * Checks artifact identity; lexical handlers are resolved only at generation time.
 */
final class LexicalProfileCheck
{
    /**
     * Rejects artifacts describing another server without imposing a witness inventory.
     * @param array<string, mixed> $profile
     * @throws RuntimeException When the artifact describes another release
     */
    public function assertCompatible(array $profile, string $dialect, string $version): void
    {
        if (($profile['dialect'] ?? null) !== $dialect || ($profile['version'] ?? null) !== $version) {
            throw new RuntimeException("Invalid lexical profile identity: {$dialect} {$version}");
        }
    }
}

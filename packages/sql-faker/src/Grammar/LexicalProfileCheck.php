<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Checks that a lexical profile describes the same release and terminals as its grammar.
 */
final class LexicalProfileCheck
{
    /**
     * Checks that a built profile describes the release and grammar it is for.
     *
     * @param array<string, mixed> $profile Profile as just built
     * @param string $dialect Dialect the profile should name
     * @param string $version Version the profile should name
     * @param list<string> $terminals Terminals the grammar can produce
     *
     * @throws RuntimeException When the profile names another release or carries no catalog
     * @throws LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertCompatible(array $profile, string $dialect, string $version, array $terminals): void
    {
        if (($profile['dialect'] ?? null) !== $dialect || ($profile['version'] ?? null) !== $version) {
            throw new RuntimeException("Invalid lexical profile identity: {$dialect} {$version}");
        }
        if (!isset($profile['catalog']) || !is_array($profile['catalog'])) {
            throw new RuntimeException("Lexical profile catalog is missing: {$dialect} {$version}");
        }

        $catalog = array_filter(
            $profile['catalog'],
            static fn (int|string $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
        (new LexicalCatalog($catalog))->assertTerminalsCovered($terminals);
    }

}

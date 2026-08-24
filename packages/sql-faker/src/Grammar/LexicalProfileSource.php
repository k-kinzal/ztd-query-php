<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Loads the checked-in lexical profile for one dialect and version.
 *
 * A profile is generated from the server's own lexer and committed as a PHP
 * file, so it is loaded rather than trusted: a file that names a different
 * dialect or version than the one asked for describes a different server, and
 * generating against it would produce SQL that only looks right.
 */
final class LexicalProfileSource
{
    /**
     * Loads the profile a dialect and version were built from.
     *
     * @param string $dialect Dialect key, e.g. "mysql"
     * @param string $version Profile version, e.g. "mysql-8.4.7"
     *
     * @return array<string, mixed> The profile as the file declares it
     *
     * @throws RuntimeException When the file is missing or describes another server
     */
    public function load(string $dialect, string $version): array
    {
        $path = SqlVersion::resolve($dialect, $version)->lexicalPath;
        if (!file_exists($path)) {
            throw new RuntimeException("Lexical profile file not found: {$path}");
        }

        /** @var array<string, mixed> $profile */
        $profile = require $path;

        if (($profile['dialect'] ?? null) !== $dialect || ($profile['version'] ?? null) !== $version) {
            throw new RuntimeException(sprintf(
                'Invalid %s lexical profile: %s',
                $this->displayName($dialect),
                $path,
            ));
        }

        return $profile;
    }

    /**
     * Spells a dialect key the way messages spell it.
     *
     * @param string $dialect Dialect key, e.g. "postgresql"
     *
     * @return string Display name, e.g. "PostgreSQL"
     */
    public function displayName(string $dialect): string
    {
        return match ($dialect) {
            'mysql' => 'MySQL',
            'postgresql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            default => $dialect,
        };
    }
}

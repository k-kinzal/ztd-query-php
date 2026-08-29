<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source;

use RuntimeException;

/**
 * Supplies the text of a server's own lexer source.
 *
 * Profiles are built from the exact source of the release they name, which puts
 * a network read in the middle of the build. Naming that boundary is what lets
 * a profile builder be exercised against text a test chose rather than against
 * whatever an upstream repository serves today.
 */
interface LexerSource
{
    /**
     * Reads one upstream file.
     *
     * @param string $url Location of the file
     *
     * @return string The file's contents
     *
     * @throws RuntimeException When the file cannot be read
     */
    public function fetch(string $url): string;
}

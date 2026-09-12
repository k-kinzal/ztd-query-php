<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Resource;

use RuntimeException;
use SqlFaker\Grammar\Resource\SqlVersion;

/**

 * Publishes one generated AST; lexical declarations are maintained in source code.

 */
final class GrammarWriter
{
    /**
     * Binds filesystem directory preparation.
     */
    public function __construct(private readonly ArtifactDirectory $directory = new ArtifactDirectory())
    {
    }

    /**
     * Stages the complete AST beside its destination and publishes it with one rename.
     * @throws RuntimeException When staging or publication fails
     */
    public function publish(SqlVersion $version, string $ast): void
    {
        $directory = $this->directory->prepared($version->astPath);
        $temporary = tempnam($directory, '.sql-faker-');
        if ($temporary === false) {
            throw new RuntimeException('Failed to stage ' . $version->astPath);
        }
        try {
            if (file_put_contents($temporary, $ast) === false || !rename($temporary, $version->astPath)) {
                throw new RuntimeException('Failed to publish ' . $version->astPath);
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}

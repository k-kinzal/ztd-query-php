<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\Node\Stmt;

/**
 * One source file, parsed and with every name resolved to its fully qualified form.
 *
 * @visibility root
 */
final class ParsedFile
{
    /**
     * @param string $path The file the statements came from
     * @param list<Stmt> $statements The top level statements
     */
    public function __construct(
        public readonly string $path,
        public readonly array $statements,
    ) {
    }
}

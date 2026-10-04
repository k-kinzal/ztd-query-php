<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `LOAD 'filename'`: a request to load a shared library into the server process.
 *
 * Rule: PG-LOAD-001. Mirrors PostgreSQL's `LoadStmt`. The file name is text
 * the server resolves in its own file system. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-load.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the library file
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("LOAD 'auto_explain'")->statement->file->value // => 'auto_explain'
 */
final class Load implements Statement
{
    use Snapshot;

    /**
     * @param StringConstant $file The library file name
     */
    public function __construct(public readonly StringConstant $file)
    {
    }

    /**
     * Derives nothing: a library file is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOAD')->node($this->file);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
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
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("LOAD 'auto_explain'")->file->value // => 'auto_explain'
 */
final class Load extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param StringConstant $file The library file name
     */
    public function __construct(public readonly StringConstant $file, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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

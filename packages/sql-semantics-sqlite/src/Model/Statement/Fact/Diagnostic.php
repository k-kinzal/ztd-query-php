<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Fact;

/**
 * A semantic problem established while analyzing grammatical SQL.
 *
 * Analysis errors carry their diagnostics in a SemanticException.
 *
 * @visibility public
 * @example Reading the problems of a statement
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $semantics->analyze('SELECT b FROM t', [$table])->facts->diagnostics[0]->message() // => 'Column b does not exist.'
 */
interface Diagnostic
{
    /**
     * Describes the problem for a person; the concrete class is the machine-readable kind.
     */
    public function message(): string;
}

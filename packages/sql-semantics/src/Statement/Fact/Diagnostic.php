<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

/**
 * A semantic problem of grammatical SQL, kept as a fact of the operation.
 *
 * Such SQL is still structured and rendered; the database would reject or
 * warn about it when it runs.
 *
 * @visibility public
 * @example Reading the problems of a statement
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
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

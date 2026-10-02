<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * The absence of a type because the SQL request itself is semantically wrong.
 *
 * @visibility public
 * @example Typing a reference to a column that the complete context does not declare
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $semantics->analyze('SELECT b FROM t', [$table])->field('b')->type instanceof \SqlSemantics\Statement\Type\Invalid // => true
 */
final class Invalid implements TypeFact
{
    use Snapshot;

    /**
     * @param Diagnostic $cause The semantic problem that leaves the expression without a type
     */
    public function __construct(public readonly Diagnostic $cause)
    {
    }
}

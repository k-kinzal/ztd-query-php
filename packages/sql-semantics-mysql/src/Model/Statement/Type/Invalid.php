<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The absence of a type because the SQL request itself is semantically wrong.
 *
 * @visibility public
 * @example Typing a reference to a column that the complete context does not declare
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $semantics->analyze('SELECT b FROM t', [$table]) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException: Column b does not exist.
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

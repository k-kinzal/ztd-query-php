<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A guarded statement list: an IF or ELSEIF condition, a searched CASE condition, or a simple CASE comparison value.
 * @visibility public
 * @example Reading an IF branch
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a > 0 THEN DO 1; END IF');
 *     count($statement->body->branches[0]->statements) // => 1
 */
final class ConditionalBranch
{
    /**
     * @var non-empty-list<ProgramStatement>
     */
    public readonly array $statements;

    /**
     * Requires a MySQL guard and at least one statement.
     * @param list<ProgramStatement> $statements
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $condition, array $statements)
    {
        Collections::objects($statements, ProgramStatement::class);
        if ($condition->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A stored program condition requires a MySQL expression.');
        }
        $this->statements = Collections::nonEmpty($statements);
    }
}

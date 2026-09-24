<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REPEAT ... UNTIL condition END REPEAT: runs its statements, then stops once the condition is true.
 * @visibility public
 * @example Reading a REPEAT loop
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) REPEAT SET a = a - 1; UNTIL a <= 0 END REPEAT');
 *     $statement->body->until instanceof \SqlSemantics\Model\Expression // => true
 */
final class RepeatStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ProgramStatement>
     */
    public readonly array $statements;

    /**
     * Requires at least one statement, a MySQL condition and a nonempty label when labeled.
     * @param list<ProgramStatement> $statements
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $label, array $statements, public readonly Expression $until)
    {
        Collections::objects($statements, ProgramStatement::class);
        if ($label === '' || $until->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A REPEAT loop requires a MySQL condition and a named label when labeled.');
        }
        $this->statements = Collections::nonEmpty($statements);
    }
}

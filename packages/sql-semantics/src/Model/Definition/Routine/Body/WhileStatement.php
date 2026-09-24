<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * WHILE condition DO ... END WHILE: runs its statements while the condition, tested before each pass, is true.
 * @visibility public
 * @example Reading a WHILE loop
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) WHILE a > 0 DO SET a = a - 1; END WHILE');
 *     $statement->body->label // => null
 *     count($statement->body->statements) // => 1
 */
final class WhileStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ProgramStatement>
     */
    public readonly array $statements;

    /**
     * Requires a MySQL condition, at least one statement and a nonempty label when labeled.
     * @param list<ProgramStatement> $statements
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $label, public readonly Expression $condition, array $statements)
    {
        Collections::objects($statements, ProgramStatement::class);
        if ($label === '' || $condition->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A WHILE loop requires a MySQL condition and a named label when labeled.');
        }
        $this->statements = Collections::nonEmpty($statements);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CASE operand WHEN value THEN ... END CASE: runs the statements of the first WHEN value equal to the operand.
 * Without ELSE, no match raises the case-not-found condition when the program runs.
 * @visibility public
 * @example Reading a simple CASE statement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN DO 1; WHEN 2 THEN DO 2; END CASE');
 *     count($statement->body->whens) // => 2
 *     $statement->body->otherwise // => []
 */
final class SimpleCaseStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ConditionalBranch>
     */
    public readonly array $whens;

    /**
     * Requires a MySQL operand and at least one WHEN branch; an empty ELSE list means no ELSE clause.
     * @param list<ConditionalBranch> $whens
     * @param list<ProgramStatement> $otherwise
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $operand, array $whens, public readonly array $otherwise = [])
    {
        Collections::objects($whens, ConditionalBranch::class);
        Collections::objects($otherwise, ProgramStatement::class);
        if ($operand->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A CASE operand requires a MySQL expression.');
        }
        $this->whens = Collections::nonEmpty($whens);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CASE WHEN condition THEN ... END CASE: runs the statements of the first true WHEN condition.
 * Without ELSE, no true condition raises the case-not-found condition when the program runs.
 * @visibility public
 * @example Reading a searched CASE statement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) CASE WHEN a > 0 THEN DO 1; ELSE DO 2; END CASE');
 *     count($statement->body->whens) // => 1
 *     count($statement->body->otherwise) // => 1
 */
final class SearchedCaseStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ConditionalBranch>
     */
    public readonly array $whens;

    /**
     * Requires at least one WHEN branch; an empty ELSE list means no ELSE clause.
     * @param list<ConditionalBranch> $whens
     * @param list<ProgramStatement> $otherwise
     * @throws InvalidStructure
     */
    public function __construct(array $whens, public readonly array $otherwise = [])
    {
        Collections::objects($whens, ConditionalBranch::class);
        Collections::objects($otherwise, ProgramStatement::class);
        $this->whens = Collections::nonEmpty($whens);
    }
}

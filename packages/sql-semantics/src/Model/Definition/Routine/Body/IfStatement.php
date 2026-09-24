<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * IF ... ELSEIF ... ELSE ... END IF: runs the statements of the first branch whose condition is true, otherwise the ELSE statements.
 * @visibility public
 * @example Reading IF branches
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a > 0 THEN DO 1; ELSEIF a < 0 THEN DO 2; ELSE DO 3; END IF');
 *     count($statement->body->branches) // => 2
 *     count($statement->body->otherwise) // => 1
 */
final class IfStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ConditionalBranch>
     */
    public readonly array $branches;

    /**
     * Requires the IF branch; an empty ELSE list means that no ELSE clause is written.
     * @param list<ConditionalBranch> $branches
     * @param list<ProgramStatement> $otherwise
     * @throws InvalidStructure
     */
    public function __construct(array $branches, public readonly array $otherwise = [])
    {
        Collections::objects($branches, ConditionalBranch::class);
        Collections::objects($otherwise, ProgramStatement::class);
        $this->branches = Collections::nonEmpty($branches);
    }
}

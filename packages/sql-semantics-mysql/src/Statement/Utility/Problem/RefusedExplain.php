<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write as W;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone explanation refusal retaining all preparation dependencies.
 *
 * @visibility public
 * @example Retaining a target and the statement that must prepare first
 *     $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name(''));
 *     $instruction = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain($query, new \SqlSemantics\Statement\Identifier\Name('JSON'), $target);
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain($instruction))->message() // => "User variable name '' is illegal"
 */
final class RefusedExplain implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredExplain $explanation The complete stored form of the refused standalone request
     */
    public function __construct(public readonly DeferredExplain $explanation)
    {
    }

    /**
     * Describes the target condition after any earlier preparation failures.
     */
    public function message(): string
    {
        $statement = $this->explanation->statement;
        if ($statement instanceof W\DeferredInsertRows || $statement instanceof W\DeferredInsertQuery) {
            return $statement->problem()->message();
        }
        if ($statement instanceof W\DeferredUpdate) {
            return (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\RefusedUpdate($statement))->message();
        }
        if ($statement instanceof W\DeferredDelete || $statement instanceof W\DeferredMultipleDelete) {
            return (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete($statement))->message();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery) {
            return $statement->problem()->message();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection) {
            return (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection($statement))->message();
        }
        if ($this->explanation->analyze && strcasecmp($this->explanation->format->value ?? '', 'TRADITIONAL') === 0) {
            return (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('EXPLAIN ANALYZE with TRADITIONAL format'))->message();
        }
        \SqlSemantics\Diagnostic\Check::invariant($this->explanation->into !== null, 'An ordinary explained operand retains an invalid target.');
        return (new InvalidExplainTarget($this->explanation->into))->message();
    }
}

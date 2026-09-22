<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Insert;
use SqlSemantics\Model\Statement\Mutation;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An ordered nonempty program of SQLite trigger query and mutation steps.
 * @visibility public
 */
final class SqliteBody
{
    /**
     * @var non-empty-list<BoundQuery|Insert\InsertValuesStatement|Insert\InsertSelectStatement|Mutation\UpdateTableStatement|Mutation\UpdateFromStatement|Mutation\DeleteTableStatement> Validated ordered operands
     */
    public readonly array $steps;

    /**
     * @param list<BoundQuery|Insert\InsertValuesStatement|Insert\InsertSelectStatement|Mutation\UpdateTableStatement|Mutation\UpdateFromStatement|Mutation\DeleteTableStatement> $steps
     * @throws InvalidStructure
     */
    public function __construct(array $steps)
    {
        Collections::objects($steps, BoundStatement::class);
        if ($steps === []) {
            throw new InvalidStructure('A SQLite trigger requires at least one action.');
        }
        foreach ($steps as $step) {
            self::check($step);
        }
        $this->steps = Collections::nonEmpty($steps);
    }

    /**
     * @throws InvalidStructure
     * @visibility SqlSemantics
     */
    public static function check(BoundStatement $step): void
    {
        if ($step->origin->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('A SQLite trigger step must use the SQLite dialect.');
        }
        if ($step instanceof BoundQuery) {
            return;
        }
        if (!$step instanceof Insert\InsertValuesStatement && !$step instanceof Insert\InsertSelectStatement && !$step instanceof Mutation\UpdateTableStatement && !$step instanceof Mutation\UpdateFromStatement && !$step instanceof Mutation\DeleteTableStatement) {
            throw new InvalidStructure('This operation cannot be a SQLite trigger step.');
        }
        if ($step->outputs !== [] || $step->ctes !== null) {
            throw new InvalidStructure('A trigger mutation cannot have RETURNING or a statement-level WITH clause.');
        }
        if (($step instanceof Mutation\UpdateTableStatement || $step instanceof Mutation\DeleteTableStatement) && ($step->orderBy !== [] || $step->limit !== null)) {
            throw new InvalidStructure('Trigger mutations cannot have ordering or pagination.');
        }
    }
}

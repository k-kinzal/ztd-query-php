<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\MutationScope;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\FromScope;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * An UPDATE of columns of the rows of a table that satisfy a predicate, optionally joined to further input.
 *
 * Rule: SQLITE-UPDATE-001. The assigned values and the predicate see the
 * written table and the relations of the FROM clause, which are derived by
 * SQLITE-FROM-SCOPE-001; the rest follows SQLITE-MUTATION-SCOPE-001.
 * Source: https://sqlite.org/lang_update.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of an UPDATE
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('UPDATE OR FAIL t SET a = u.b FROM u WHERE t.id = u.id');
 *     [$update->statement->resolution, count($update->statement->assignments), $update->statement->from->name->name->value] // => [\SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution::Fail, 1, 'u']
 * @example Refusing an UPDATE that assigns nothing
 *     new \SqlSemantics\Platform\Sqlite\Statement\Mutation\Update(new \SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Update implements Statement, Selection
{
    use Snapshot;

    /**
     * @var non-empty-list<Assignment|RowAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<ResultColumn|Star|TableStar> The RETURNING columns in written order
     */
    public readonly array $returning;

    /**
     * @param MutationTarget $target The written table
     * @param list<Assignment|RowAssignment> $assignments The assignments in written order; at least one
     * @param Relation|null $from The further input relation
     * @param Scalar|null $where The row predicate
     * @param list<ResultColumn|Star|TableStar> $returning The RETURNING columns
     * @param ConflictResolution|null $resolution The algorithm written after UPDATE OR
     * @param WithClause|null $with The WITH clause of the statement
     * @throws InvalidConstruction When an assignment or a RETURNING column is of no admitted class
     */
    public function __construct(
        public readonly MutationTarget $target,
        array $assignments,
        public readonly ?Relation $from = null,
        public readonly ?Scalar $where = null,
        array $returning = [],
        public readonly ?ConflictResolution $resolution = null,
        public readonly ?WithClause $with = null,
    ) {
        $assigned = [];
        foreach ($assignments as $assignment) {
            if (!$assignment instanceof Assignment && !$assignment instanceof RowAssignment) {
                throw new InvalidConstruction('An UPDATE assigns columns or column rows.');
            }
            $assigned[] = $assignment;
        }
        $list = [];
        foreach ($returning as $column) {
            if (!$column instanceof ResultColumn && !$column instanceof Star && !$column instanceof TableStar) {
                throw new InvalidConstruction('A RETURNING column is an expression, a star or a qualified star.');
            }
            $list[] = $column;
        }
        Check::input($assigned !== [] && array_is_list($assignments) && array_is_list($returning), 'An UPDATE has at least one assignment.');
        $this->assignments = $assigned;
        $this->returning = $list;
    }

    /**
     * Answers the further input relation of the FROM clause.
     */
    public function input(): ?Relation
    {
        return $this->from;
    }

    /**
     * Derives the statement as a root and records the rows of RETURNING as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $this->deriveWithin($derivation, $derivation->environment());
        if ($fact !== null) {
            $derivation->output($fact);
        }
    }

    /**
     * Derives the statement inside an environment, as a trigger program does, and answers the rows it returns.
     */
    public function deriveWithin(Derivation $derivation, Environment $outer): ?QueryFact
    {
        $scope = new MutationScope();
        [$base, $target] = $scope->open($this->target, $this->with, $derivation, $outer);
        $inputs = $this->from === null ? [] : (new FromScope())->open($this->from, $derivation, $base, [$target], false)->visible;
        $environment = new Environment($derivation->context, $base, [$target, ...$inputs]);
        $scope->assign($this->assignments, $target, $derivation, $environment);
        if ($this->where !== null) {
            $derivation->scalar($this->where, $environment);
        }

        return $scope->returning($this->returning, $target, $derivation, $base);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('UPDATE');
        if ($this->resolution !== null) {
            $out->keyword('OR', $this->resolution->value);
        }
        $out->node($this->target)->keyword('SET')->list($this->assignments);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}

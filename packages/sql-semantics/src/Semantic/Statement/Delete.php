<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

use SqlSemantics\Core\Analysis\ModelGraph;
use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Operands;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;
use SqlSemantics\Semantic\Relation\TableReference;
use SqlSemantics\Semantic\Scope;

/**
 * Deletes rows selected by a predicate over one target relation.
 *
 * @visibility public
 * @example Inspecting a deletion target
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('DELETE FROM bar WHERE foo = 1');
 *     $statement->table->name->name->value // => 'bar'
 */
final class Delete
{
    /**
     * The relation occurrence whose rows are deleted.
     */
    public readonly TableReference $table;

    /**
     * Asserts that the predicate belongs to the single target relation's scope.
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf|null $where = null,
    ) {
        assert(count($scope->sources) === 1 && $scope->sources[0] instanceof TableReference, 'A single-table DELETE requires one table occurrence.');
        $this->table = $scope->sources[0];
        if ($where !== null) {
            Operands::check($scope, $where);
        }
        assert((new ModelGraph())->isImmutable($this), 'A statement contains only immutable semantic values.');
    }

    /**
     * Returns a deletion with a replacement predicate in the same target scope.
     */
    public function withWhere(ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf|null $where): self
    {
        return new self($this->scope, $where);
    }

    /**
     * Reconstructs the deletion from its target and predicate.
     */
    public function toString(): string
    {
        return 'DELETE FROM ' . $this->table->toString() . ($this->where === null ? '' : ' WHERE ' . $this->where->toString());
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Operands;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;
use SqlSemantics\Semantic\Projection\Field;
use SqlSemantics\Semantic\Projection\Fields;
use SqlSemantics\Semantic\Projection\Ordering;
use SqlSemantics\Semantic\Projection\OutputReference;
use SqlSemantics\Semantic\Relation\TableReference;
use SqlSemantics\Semantic\Scope;

/**
 * A projection over its relation occurrences, independent of parsing.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo FROM bar');
 *     $statement->toString() // => 'SELECT foo FROM bar'
 *
 * @visibility public
 */
final class Select
{
    /**
     * The namespace in which the represented inputs are evaluated.
     */
    public readonly Scope $scope;
    /**
     * @var list<TableReference>
     */
    public readonly array $tables;

    /**
     * @param list<Ordering> $orderBy
     */
    public function __construct(
        private readonly Fields $projection,
        public readonly bool $distinct = false,
        public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf|null $where = null,
        public readonly array $orderBy = [],
        public readonly Literal|Parameter|null $limit = null,
        public readonly Literal|Parameter|null $offset = null,
    ) {
        $this->scope = $projection->scope;
        $this->tables = $this->scope->tables;
        assert((new \SqlSemantics\Core\Analysis\ModelGraph())->isImmutable($this), 'A statement contains only immutable semantic values.');
        if ($where !== null) {
            Operands::check($this->scope, $where);
        }
        foreach ($orderBy as $ordering) {
            if ($ordering->expression instanceof OutputReference) {
                assert(($projection->outputs()[$ordering->expression->position - 1] ?? null) === $ordering->expression->field, 'A result sort key must reference its actual output position.');
            } else {
                Operands::check($this->scope, $ordering->expression);
            }
        }
    }

    /**
     * Returns the immutable, ordered projection collection.
     */
    public function fields(): Fields
    {
        return $this->projection;
    }

    /**
     * Returns the uniquely named result field; duplicate names remain ambiguous.
     */
    public function field(string $name): Field
    {
        return $this->projection->field($name);
    }

    /**
     * Returns a new SELECT while asserting that the replacement preserves scope references.
     */
    public function withFields(Fields $fields): self
    {
        assert($fields->scope === $this->scope, 'Replacing fields must preserve the statement scope.');
        return new self($fields, $this->distinct, $this->where, $this->orderBy, $this->limit, $this->offset);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $this->projection->toString();
        $sql .= $this->scope->sources === [] ? '' : ' FROM ' . implode(', ', array_map(static fn ($source): string => $source->toString(), $this->scope->sources));
        $sql .= $this->where === null ? '' : ' WHERE ' . $this->where->toString();
        $sql .= $this->orderBy === [] ? '' : ' ORDER BY ' . implode(', ', array_map(static fn (Ordering $order): string => $order->toString(), $this->orderBy));
        $sql .= $this->limit === null ? '' : ' LIMIT ' . $this->limit->toString();
        return $sql . ($this->offset === null ? '' : ' OFFSET ' . $this->offset->toString());
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Relation;

use SqlSemantics\Core\Model\JoinKind;
use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;

/**
 * Matching and NULL extension of two relation inputs.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a.foo FROM bar a LEFT JOIN bar b ON a.foo = b.foo');
 *     $statement->scope->sources[0]->kind->value // => 'left'
 *
 * @visibility public
 */
final class Join
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(
        public readonly JoinKind $kind,
        public readonly TableReference|self $left,
        public readonly TableReference|self $right,
        public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf|null $condition = null,
    ) {
        assert($kind === JoinKind::Cross || $condition !== null, 'A qualified join requires its match condition.');
        assert($kind !== JoinKind::Cross || $condition === null, 'A cross join has no match condition.');
        if ($condition !== null && !$condition instanceof Literal && !$condition instanceof Parameter) {
            assert($condition->scope->tables === [...Relations::tables($left), ...Relations::tables($right)], 'ON can reference only these join inputs.');
        }
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->left->toString() . ' ' . strtoupper($this->kind->value) . ' JOIN ' . ($this->right instanceof self ? '(' . $this->right->toString() . ')' : $this->right->toString()) . ($this->condition === null ? '' : ' ON ' . $this->condition->toString());
    }
}

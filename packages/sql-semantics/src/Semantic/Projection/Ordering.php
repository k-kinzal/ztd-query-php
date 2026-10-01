<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Projection;

use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;

/**
 * A result ordering and its explicit NULL placement.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS one ORDER BY one DESC');
 *     $statement->orderBy[0]->descending // => true
 *
 * @visibility public
 */
final class Ordering
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(
        public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf|OutputReference $expression,
        public readonly ?bool $descending = null,
        public readonly ?bool $nullsFirst = null,
    ) {
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->expression->toString() . ($this->descending === null ? '' : ($this->descending ? ' DESC' : ' ASC')) . ($this->nullsFirst === null ? '' : ($this->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST'));
    }
}

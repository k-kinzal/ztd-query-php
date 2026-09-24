<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;

/**
 * MySQL `VALUES(column)`: the value an INSERT proposed for a column, read in ON DUPLICATE KEY UPDATE (NULL elsewhere).
 * @visibility public
 * @example Reading the column whose proposed value is used
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t (id, n) VALUES (1, 2) ON DUPLICATE KEY UPDATE n = VALUES(n)');
 *     $statement->toString() // => 'INSERT INTO `t`(`id`, `n`) VALUES (1, 2) ON DUPLICATE KEY UPDATE `n` = VALUES (`n`)'
 */
final class ProposedColumn extends Expression
{
    /**
     * Takes the column's type; the value may be NULL because it is NULL outside ON DUPLICATE KEY UPDATE.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $column)
    {
        if ($column->type->dialect !== Dialect::MySql || !in_array($column->kind, [ExpressionKind::Column, ExpressionKind::UnresolvedColumn], true)) {
            throw new InvalidStructure('VALUES() names a MySQL column.');
        }
        parent::__construct(new ExpressionFacts($column->type, Nullability::MaybeNull), $source);
    }

    /**
     * Identifies a column value reference.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Column;
    }

    /**
     * @return list<Expression> The named column
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->column];
    }

    /**
     * Returns the fixed function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'VALUES';
    }

    /**
     * Preserves the facts derived from the column.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Proposed value facts are derived from its column.');
        }
        return new static($this->source, $this->column);
    }
}

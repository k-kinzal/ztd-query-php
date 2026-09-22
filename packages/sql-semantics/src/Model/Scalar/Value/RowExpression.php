<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * An ordered record of field expressions; only PostgreSQL permits fewer than two fields.
 * @visibility public
 * @example Reading a record's fields
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT ROW(1, 2)');
 *     count($statement->outputs[0]->expression->items) // => 2
 */
final class RowExpression extends Expression
{
    /**
     * @param list<Expression> $items
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly array $items,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($items, Expression::class);
        if (count($items) < 2 && $facts->type->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A row constructor requires at least two fields outside PostgreSQL.');
        }
        if ($facts->type->identity !== \SqlSemantics\Type\Identity\BuiltinIdentity::Record) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A row constructor has a record result type.');
        }
        parent::__construct($facts, $source);
        foreach ($this->inputs() as $input) {
            if ($input->type->dialect !== $facts->type->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Row;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return $this->items;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return 'ROW';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->items);
    }

}

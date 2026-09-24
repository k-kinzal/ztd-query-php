<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\RowsFrom;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A result column of a function table: a column of one invocation, or the ordinality column when no invocation is given.
 * @visibility public
 * @example Reading the ordinality column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT * FROM generate_series(1, 3) WITH ORDINALITY');
 *     $column = $statement->from->outputs[1]->expression;
 *     [$column->function, $column->spelling(), $column->type->name] // => [null, 'ordinality', 'bigint']
 */
final class RowsFromColumn extends Expression
{
    /**
     * @param int|null $function Position of the producing invocation; null for the ordinality column
     * @param string $name Column name before relation aliases apply
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly RowsFromTable $table, public readonly ?int $function, public readonly string $name)
    {
        if ($function === null ? !$table->ordinality : !isset($table->functions[$function])) {
            throw new InvalidStructure('A function table column requires its producing invocation or the ordinality column.');
        }
        if ($name === '') {
            throw new InvalidStructure('A function table column requires a nonempty name.');
        }
        parent::__construct($facts, $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::FunctionColumn;
    }

    /**
     * @return list<Expression> The producing invocation; the ordinality column has no inputs
     */
    #[Override]
    public function inputs(): array
    {
        return $this->function === null ? [] : [$this->table->functions[$this->function]->call];
    }

    /**
     * Returns the column name.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->name;
    }

    /**
     * Retains the producing invocation with replacement facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new self($facts, $this->source, $this->table, $this->function, $this->name);
    }
}

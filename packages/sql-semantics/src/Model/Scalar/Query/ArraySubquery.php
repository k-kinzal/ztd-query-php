<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ArrayConstructor;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\RowShape;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A PostgreSQL ARRAY(subquery) that collects the single result column of every row into one array.
 * @visibility public
 * @example Reading the collected query and the derived array type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT ARRAY(SELECT 1)');
 *     $statement->outputs[0]->expression->query instanceof \SqlSemantics\Model\BoundSelect // => true
 *     $statement->outputs[0]->expression->type->name // => 'integer[]'
 */
final class ArraySubquery extends Expression
{
    /**
     * Derives a non-NULL array of the query's only result column type.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly BoundQuery $query)
    {
        $width = RowShape::width($query);
        if ($query->origin->dialect !== Dialect::PostgreSql || $width !== null && $width !== 1) {
            throw new InvalidStructure('ARRAY(subquery) requires a PostgreSQL query with exactly one result column.');
        }
        $column = $query->resultColumns()[0]->expression->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown');
        parent::__construct(new ExpressionFacts(ArrayConstructor::arrayOf($column), Nullability::NotNull), $source);
    }

    /**
     * Identifies an array collected from a query.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ArraySubquery;
    }

    /**
     * @return list<Expression> The query result column expressions
     */
    #[Override]
    public function inputs(): array
    {
        return array_map(static fn ($output): Expression => $output->expression, $this->query->resultColumns());
    }

    /**
     * Returns the fixed constructor keyword.
     */
    #[Override]
    public function spelling(): string
    {
        return 'ARRAY';
    }

    /**
     * Preserves the facts derived from the query.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Array subquery facts are derived from its query.');
        }
        return new static($this->source, $this->query);
    }

    /**
     * Returns the required nested query that supplies the elements.
     */
    #[Override]
    public function subquery(): BoundQuery
    {
        return $this->query;
    }
}

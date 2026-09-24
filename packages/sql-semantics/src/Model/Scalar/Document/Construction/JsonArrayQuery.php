<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\RowShape;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL SQL/JSON JSON_ARRAY over a query: builds an array of the only result column of every row, without running the query.
 * The result is json unless RETURNING says otherwise; a query without rows yields NULL.
 * @visibility public
 * @example Reading the collected query
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT JSON_ARRAY(SELECT 1 RETURNING jsonb)');
 *     $value = $query->outputs[0]->expression;
 *     [$value->query instanceof \SqlSemantics\Model\BoundSelect, $value->type->name] // => [true, 'jsonb']
 */
final class JsonArrayQuery extends Expression
{
    /**
     * @param Format|null $format FORMAT JSON declared for the query's column
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly BoundQuery $query,
        public readonly ?Format $format = null,
        public readonly ?JsonReturning $returning = null,
    ) {
        $width = RowShape::width($query);
        if ($query->origin->dialect !== Dialect::PostgreSql || $width !== null && $width !== 1) {
            throw new InvalidStructure('JSON_ARRAY(query) requires a PostgreSQL query with exactly one result column.');
        }
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), Nullability::MaybeNull), $source);
    }

    /**
     * Identifies a SQL/JSON constructor.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonConstructor;
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
     * Returns the fixed constructor name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_ARRAY';
    }

    /**
     * Preserves the facts derived from the returned type.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_ARRAY(query) facts are derived from its returned type.');
        }
        return new static($this->source, $this->query, $this->format, $this->returning);
    }
}

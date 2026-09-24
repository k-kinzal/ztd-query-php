<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\Scalar\Document\SqlJsonInvariant;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL JSON_ARRAYAGG: aggregates one element per row into an array in the given order, without evaluating it.
 * The result is json unless RETURNING says otherwise; a group without rows yields NULL. OVER makes it a `WindowCall`.
 * @visibility public
 * @example Reading the element, its ordering and the NULL handling
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(v integer)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT JSON_ARRAYAGG(v ORDER BY v DESC NULL ON NULL RETURNING jsonb) FROM t');
 *     $value = $query->outputs[0]->expression;
 *     [$value->element->expression->referenceParts(), $value->orderBy[0]->descending, $value->onNull, $value->type->name] // => [['v'], true, \SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling::Null, 'jsonb']
 */
final class JsonArrayAggregate extends Expression
{
    /**
     * @param list<Ordering> $orderBy Element order within the group
     * @param Expression|null $filter FILTER condition choosing the aggregated rows
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly Input $element,
        public readonly array $orderBy = [],
        public readonly JsonNullHandling $onNull = JsonNullHandling::Absent,
        public readonly ?JsonReturning $returning = null,
        public readonly ?Expression $filter = null,
    ) {
        Collections::objects($orderBy, Ordering::class);
        foreach ($orderBy as $order) {
            if (!$order->key instanceof Expression) {
                throw new InvalidStructure('A JSON_ARRAYAGG ordering requires an input expression, not a result alias or position.');
            }
        }
        SqlJsonInvariant::postgreSql('JSON_ARRAYAGG', $this->inputs());
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), Nullability::MaybeNull, SqlJsonInvariant::extensions($this->inputs())), $source);
    }

    /**
     * Identifies an aggregate.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Aggregate;
    }

    /**
     * @return list<Expression> The element, the ordering keys and the FILTER condition
     */
    #[Override]
    public function inputs(): array
    {
        $keys = [];
        foreach ($this->orderBy as $order) {
            if ($order->key instanceof Expression) {
                $keys[] = $order->key;
            }
        }
        return [$this->element->expression, ...$keys, ...($this->filter === null ? [] : [$this->filter])];
    }

    /**
     * Returns the fixed aggregate name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_ARRAYAGG';
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
            throw new InvalidStructure('JSON_ARRAYAGG facts are derived from its returned type.');
        }
        return new static($this->source, $this->element, $this->orderBy, $this->onNull, $this->returning, $this->filter);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\Scalar\Document\SqlJsonInvariant;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL JSON_OBJECTAGG: aggregates one key/value member per row into an object, without evaluating it.
 * The result is json unless RETURNING says otherwise; a group without rows yields NULL. OVER makes it a `WindowCall`.
 * @visibility public
 * @example Reading the aggregated member and filter
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(k text, v integer)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT JSON_OBJECTAGG(k : v ABSENT ON NULL) FILTER (WHERE v > 0) FROM t');
 *     $value = $query->outputs[0]->expression;
 *     [$value->member->key->referenceParts(), $value->onNull, $value->filter !== null, $value->type->name] // => [['k'], \SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling::Absent, true, 'json']
 */
final class JsonObjectAggregate extends Expression
{
    /**
     * @param bool $uniqueKeys Whether WITH UNIQUE KEYS rejects a repeated key
     * @param Expression|null $filter FILTER condition choosing the aggregated rows
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly JsonMember $member,
        public readonly JsonNullHandling $onNull = JsonNullHandling::Null,
        public readonly bool $uniqueKeys = false,
        public readonly ?JsonReturning $returning = null,
        public readonly ?Expression $filter = null,
    ) {
        SqlJsonInvariant::postgreSql('JSON_OBJECTAGG', $this->inputs());
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
     * @return list<Expression> The key, the value and the FILTER condition
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->member->key, $this->member->value->expression, ...($this->filter === null ? [] : [$this->filter])];
    }

    /**
     * Returns the fixed aggregate name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_OBJECTAGG';
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
            throw new InvalidStructure('JSON_OBJECTAGG facts are derived from its returned type.');
        }
        return new static($this->source, $this->member, $this->onNull, $this->uniqueKeys, $this->returning, $this->filter);
    }
}

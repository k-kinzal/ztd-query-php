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
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL SQL/JSON JSON_OBJECT: builds an object from ordered key/value members, without evaluating them.
 * The result is json unless RETURNING says otherwise, and is never NULL. Without members there is no NULL or key-uniqueness clause.
 * @visibility public
 * @example Reading the members and options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_OBJECT('a': 1, 'b' VALUE NULL ABSENT ON NULL WITH UNIQUE KEYS RETURNING jsonb)");
 *     $value = $query->outputs[0]->expression;
 *     [count($value->members), $value->onNull, $value->uniqueKeys, $value->type->name] // => [2, \SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling::Absent, true, 'jsonb']
 */
final class JsonObjectConstructor extends Expression
{
    /**
     * @param list<JsonMember> $members Ordered members; an empty list is `JSON_OBJECT()`
     * @param bool $uniqueKeys Whether WITH UNIQUE KEYS rejects a repeated key
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly array $members,
        public readonly JsonNullHandling $onNull = JsonNullHandling::Null,
        public readonly bool $uniqueKeys = false,
        public readonly ?JsonReturning $returning = null,
    ) {
        Collections::objects($members, JsonMember::class);
        if ($members === [] && ($onNull !== JsonNullHandling::Null || $uniqueKeys)) {
            throw new InvalidStructure('JSON_OBJECT() without members has no NULL or key-uniqueness clause.');
        }
        SqlJsonInvariant::postgreSql('JSON_OBJECT', $this->inputs());
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), Nullability::NotNull), $source);
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
     * @return list<Expression> Each member's key and value in written order
     */
    #[Override]
    public function inputs(): array
    {
        $inputs = [];
        foreach ($this->members as $member) {
            array_push($inputs, $member->key, $member->value->expression);
        }
        return $inputs;
    }

    /**
     * Returns the fixed constructor name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_OBJECT';
    }

    /**
     * Preserves the non-NULL facts of the constructed object.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_OBJECT facts are derived from its returned type.');
        }
        return new static($this->source, $this->members, $this->onNull, $this->uniqueKeys, $this->returning);
    }
}

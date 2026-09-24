<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\TableFunction\Json\Response\ValueResponse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * JSON_VALUE: extracts the scalar at a path of a JSON document as the returned type, without evaluating it.
 * MySQL takes a string-literal path; PostgreSQL takes any path expression, an optional document FORMAT and PASSING variables.
 * Without RETURNING the result is a MySQL string or PostgreSQL text; without an ON EMPTY or ON ERROR response the database returns NULL.
 * @visibility public
 * @example Inspecting the document, path, returned type and responses
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_VALUE('{\"a\": 1}', '$.a' RETURNING UNSIGNED ERROR ON EMPTY)");
 *     $value = $query->outputs[0]->expression;
 *     $value->path->spelling() // => "'$.a'"
 *     $value->returning?->name // => 'bigint unsigned'
 *     $value->onEmpty // => \SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Error
 *     $value->onError // => \SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Default
 * @example Reading PostgreSQL PASSING variables
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_VALUE(jsonb '[1]', '$[\$i]' PASSING 0 AS i RETURNING integer NULL ON ERROR)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->passing[0]->name, $value->type->name, $value->onError] // => ['i', 'integer', \SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Null]
 */
final class JsonScalarExtraction extends Expression
{
    /**
     * Derives the result from the returned type; a missing or invalid value yields NULL unless a response says otherwise.
     * @param list<PassingArgument> $passing PostgreSQL path variables in written order
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly Expression $document,
        public readonly Expression $path,
        public readonly ?TypeDescriptor $returning = null,
        public readonly ValueResponse $onEmpty = ValueBehavior::Default,
        public readonly ValueResponse $onError = ValueBehavior::Default,
        public readonly ?Format $format = null,
        public readonly array $passing = [],
    ) {
        Collections::objects($passing, PassingArgument::class);
        $dialect = $document->type->dialect;
        if ($dialect === Dialect::Sqlite) {
            throw new InvalidStructure('JSON_VALUE requires MySQL or PostgreSQL.');
        }
        SqlJsonInvariant::dialect('JSON_VALUE', $dialect, [$path, ...SqlJsonInvariant::passed($passing), ...SqlJsonInvariant::defaults($onEmpty, $onError)]);
        if ($returning !== null && $returning->dialect !== $dialect) {
            throw new InvalidStructure('JSON_VALUE returns a type of its document dialect.');
        }
        if ($dialect === Dialect::MySql && (!$path instanceof Literal || $path->literalKind !== LiteralKind::Text || $format !== null || $passing !== [])) {
            throw new InvalidStructure('A MySQL JSON_VALUE path is a string literal, without document format or PASSING variables.');
        }
        foreach ([$onEmpty, $onError] as $response) {
            if ($response === ValueBehavior::EmptyArray || $response === ValueBehavior::EmptyObject) {
                throw new InvalidStructure('A JSON_VALUE response is NULL, ERROR or a DEFAULT expression.');
            }
        }
        $default = TypeDescriptor::builtin($dialect, $dialect === Dialect::MySql ? 'varchar' : 'text');
        parent::__construct(new ExpressionFacts($returning ?? $default, Nullability::MaybeNull, $document->nullExtendedBy), $source);
    }

    /**
     * Identifies a JSON scalar extraction.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonValue;
    }

    /**
     * @return list<Expression> The document, the path, the PASSING values and any DEFAULT response values
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->document, $this->path, ...SqlJsonInvariant::passed($this->passing), ...SqlJsonInvariant::defaults($this->onEmpty, $this->onError)];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_VALUE';
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
            throw new InvalidStructure('JSON_VALUE facts are derived from its returned type.');
        }
        return new static($this->source, $this->document, $this->path, $this->returning, $this->onEmpty, $this->onError, $this->format, $this->passing);
    }
}

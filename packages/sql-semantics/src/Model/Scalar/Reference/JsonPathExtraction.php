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
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A MySQL inline JSON path on a column: `column->'path'` extracts JSON, `column->>'path'` also unquotes it.
 * In a SHOW ... WHERE filter the column is a result column of the inspection.
 * @visibility public
 * @example Reading the column, the path and the unquoting
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (doc JSON)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT doc->>'$.name' FROM t");
 *     $path = $query->outputs[0]->expression;
 *     $path->column->columnBinding()?->column->name // => 'doc'
 *     $path->path->text // => "'$.name'"
 *     $path->unquoted // => true
 *     $path->type->name // => 'longtext'
 */
final class JsonPathExtraction extends Expression
{
    /**
     * Derives a JSON result, or a text result when unquoted, that is NULL when the path does not match.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $column, public readonly Literal $path, public readonly bool $unquoted)
    {
        if ($column->type->dialect !== Dialect::MySql || $path->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Inline JSON paths require MySQL.');
        }
        if (!in_array($column->kind, [ExpressionKind::Column, ExpressionKind::UnresolvedColumn], true) && !$column instanceof \SqlSemantics\Model\Query\Inspection\MetadataColumn) {
            throw new InvalidStructure('An inline JSON path applies to a column reference or a result column of an inspection filter.');
        }
        if ($path->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('An inline JSON path is a string literal.');
        }
        $type = TypeDescriptor::builtin(Dialect::MySql, $unquoted ? 'longtext' : 'json');
        parent::__construct(new ExpressionFacts($type, Nullability::MaybeNull, $column->nullExtendedBy), $source);
    }

    /**
     * Identifies a JSON path extraction.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonPath;
    }

    /**
     * @return list<Expression> The column followed by the path literal
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->column, $this->path];
    }

    /**
     * Returns the inline operator.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->unquoted ? '->>' : '->';
    }

    /**
     * Preserves the facts derived from the column and the operator.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Inline JSON path facts are derived from its operands.');
        }
        return new static($this->source, $this->column, $this->path, $this->unquoted);
    }
}

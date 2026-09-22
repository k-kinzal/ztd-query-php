<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * ColumnReference has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting ColumnReference
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT t.id FROM t');
 *     $value = $query->outputs[0]->expression;
 *     $value instanceof \SqlSemantics\Model\Scalar\Reference\ColumnReference // => true
 */
final class ColumnReference extends Expression
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $name;

    /**
     * @param list<Expression> $origins
     * @param list<string> $name
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly \SqlSemantics\Model\ColumnBinding $binding,
        public readonly array $origins,
        array $name,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($name);
        if ($name === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->name = $name;
        \SqlSemantics\Model\Validation\Collections::objects($origins, Expression::class);
        \SqlSemantics\Model\Validation\Collections::strings($name);
        if (serialize($facts->type) !== serialize($binding->column->type)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A bound reference must retain its declaration type.');
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
        return ExpressionKind::Column;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return $this->origins;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): ?string
    {
        return null;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->binding, $this->origins, $this->name);
    }

    /**
     * Returns the resolved relation occurrence and declared column symbol.
     */
    #[Override]
    public function columnBinding(): \SqlSemantics\Model\ColumnBinding
    {
        return $this->binding;
    }

    /**
     * @return non-empty-list<string>
     * @visibility SqlSemantics
     */
    #[Override]
    public function referenceParts(): array
    {
        return $this->name;
    }
}

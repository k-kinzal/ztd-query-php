<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * UnresolvedColumnReference has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting UnresolvedColumnReference
 *     $boundQuery1 = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT missing', strict: false);
 *     $expression = $boundQuery1->outputs[0]->expression;
 *     $expression instanceof \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference // => true
 */
final class UnresolvedColumnReference extends Expression
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $name;

    /**
     * @param list<string> $name
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        array $name,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($name);
        if ($name === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->name = $name;
        if ($facts->type->name !== 'unknown') {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An unresolved column needs its name and unresolved type.');
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
        return ExpressionKind::UnresolvedColumn;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return implode('.', $this->name);
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->name);
    }

    /**
     * Returns unquoted identifier parts that identify this reference.
     */
    #[Override]
    public function referenceParts(): array
    {
        return $this->name;
    }
}

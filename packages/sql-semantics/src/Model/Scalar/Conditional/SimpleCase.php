<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * SimpleCase has explicit semantic operands and a fixed expression category.
 * @visibility public
 */
final class SimpleCase extends Expression
{
    /**
     * @param list<When> $branches
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly Expression $value,
        public readonly array $branches,
        public readonly ?Expression $otherwise,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($branches, When::class);
        if ($branches === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('CASE needs at least one WHEN branch.');
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
        return ExpressionKind::CaseExpression;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, ...array_merge(...array_map(static fn (When $branch): array => [$branch->test, $branch->result], $this->branches)), ...($this->otherwise === null ? [] : [$this->otherwise])];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return 'CASE';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->branches, $this->otherwise);
    }

}

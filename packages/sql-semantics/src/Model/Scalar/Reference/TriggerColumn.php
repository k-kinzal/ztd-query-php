<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Trigger\RowVersion;

/**
 * A column read from a trigger's OLD or NEW row image, without reading its runtime value.
 * @visibility public
 */
final class TriggerColumn extends Expression
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly ColumnBinding $binding, public readonly RowVersion $version)
    {
        parent::__construct($facts, $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::TriggerColumn;
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
        return new static($facts, $this->source, $this->binding, $this->version);
    }

    #[Override]
    public function columnBinding(): ColumnBinding
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
        return [$this->version->value, $this->binding->column->name];
    }
}

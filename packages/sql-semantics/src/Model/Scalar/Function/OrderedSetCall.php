<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An ordered-set aggregate separates group arguments from ordered row inputs.
 * @visibility public
 * @example Reading a percentile's direct and ordered inputs
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(score INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY score) FROM t');
 *     $query->outputs[0]->expression->directArguments[0]->spelling() // => '0.5'
 */
final class OrderedSetCall extends Expression
{
    /**
     * @var non-empty-list<Ordering> Input row ordering required by this operation
     */
    public readonly array $withinGroup;

    /**
     * @param list<Expression> $directArguments Arguments evaluated once for the group
     * @param list<Ordering> $withinGroup Ordered values evaluated for each input row
     * @throws InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        Node|Token $source,
        public readonly FunctionReference $function,
        public readonly array $directArguments,
        array $withinGroup,
        public readonly ?Expression $filter = null,
    ) {
        if ($facts->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('WITHIN GROUP aggregates require PostgreSQL.');
        }
        Collections::objects($directArguments, Expression::class);
        Collections::objects($withinGroup, Ordering::class);
        $this->withinGroup = Collections::nonEmpty($withinGroup);
        foreach ($withinGroup as $ordering) {
            if (!$ordering->key instanceof Expression) {
                throw new InvalidStructure('WITHIN GROUP orders input expressions, not result columns.');
            }
        }
        parent::__construct($facts, $source);
        \SqlSemantics\Model\Validation\StatementOperands::expressions($this->inputs(), $facts->type->dialect);
    }

    /**
     * Returns the aggregate operation category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Aggregate;
    }

    /**
     * @return list<Expression> Group arguments followed by ordered row inputs and the filter
     */
    #[Override]
    public function inputs(): array
    {
        return [...$this->directArguments, ...array_map(static fn (Ordering $order): Expression => $order->key instanceof Expression ? $order->key : throw new InvalidStructure('An ordered aggregate requires input expressions.'), $this->withinGroup), ...($this->filter === null ? [] : [$this->filter])];
    }

    /**
     * Returns the referenced aggregate name for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return strtoupper(implode('.', $this->function->name()->parts));
    }

    /**
     * Replaces inferred result facts while preserving every invocation operand.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->function, $this->directArguments, $this->withinGroup, $this->filter);
    }
}

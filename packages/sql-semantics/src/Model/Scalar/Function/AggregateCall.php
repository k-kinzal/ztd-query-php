<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * AggregateCall has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting AggregateCall
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('SELECT f(ALL) OVER (base PARTITION BY g(ALL) OVER named ORDER BY h(ALL) FILTER (WHERE ?1) OVER another)', strict: false);
 *     $value = $statement->outputs[0]->expression;
 *     $value->function instanceof \SqlSemantics\Model\Scalar\Function\AggregateCall // => true
 * @example Reading the ordering and separator of a MySQL GROUP_CONCAT
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a TEXT, b INT)'));
 *     $call = $binder->bind("SELECT group_concat(DISTINCT a ORDER BY b DESC SEPARATOR '; ') FROM t")->outputs[0]->expression;
 *     [$call->mode, count($call->arguments), $call->orderBy[0]->descending, $call->separator?->text] // => [\SqlSemantics\Model\Scalar\Function\ArgumentMode::Distinct, 1, true, "'; '"]
 * @example Reading a GROUP_CONCAT ordering by argument position
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a TEXT, b INT)'));
 *     $call = $binder->bind("SELECT group_concat(a, b ORDER BY 2) FROM t")->outputs[0]->expression;
 *     $call->orderBy[0]->key->output->expression === $call->arguments[1] // => true
 */
final class AggregateCall extends Expression
{
    /**
     * @param list<Expression> $arguments
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @param \SqlSemantics\Model\Scalar\Value\Literal|null $separator Explicit MySQL GROUP_CONCAT SEPARATOR string; null leaves the default comma
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly FunctionReference $function,
        public readonly array $arguments,
        public readonly ArgumentMode $mode,
        public readonly array $orderBy,
        public readonly ?Expression $filter,
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $separator = null,
    ) {
        if ($separator !== null && ($facts->type->dialect !== \SqlSemantics\Dialect::MySql || strtoupper(implode('.', $function->name()->parts)) !== 'GROUP_CONCAT' || !in_array($separator->literalKind, [\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, \SqlSemantics\Model\Scalar\Value\LiteralKind::Binary, \SqlSemantics\Model\Scalar\Value\LiteralKind::BitString], true))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only MySQL GROUP_CONCAT takes a SEPARATOR, and it is a string, hexadecimal, or bit literal.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($arguments, Expression::class);
        Argument\ArgumentOrder::validate($arguments);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        foreach ($orderBy as $order) {
            $position = $order->key instanceof \SqlSemantics\Model\Query\Ordering\OutputPosition ? $order->key->output : null;
            if (!$order->key instanceof Expression && ($position === null || !self::concatenation($function, $facts) || ($arguments[$position->ordinal] ?? null) !== $position->expression)) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('This ordering requires an input expression; only a MySQL GROUP_CONCAT orders by the position of one of its arguments.');
            }
        }
        parent::__construct($facts, $source);
        foreach ($this->inputs() as $input) {
            if ($input->type->dialect !== $facts->type->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
    }

    /**
     * Whether the call is MySQL's GROUP_CONCAT, whose ORDER BY position refers to one of its own arguments.
     */
    public static function concatenation(FunctionReference $function, ExpressionFacts $facts): bool
    {
        return $facts->type->dialect === \SqlSemantics\Dialect::MySql && strtoupper(implode('.', $function->name()->parts)) === 'GROUP_CONCAT';
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Aggregate;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        $keys = array_values(array_filter(array_map(static fn (\SqlSemantics\Model\Ordering $order): ?Expression => $order->key instanceof Expression ? $order->key : null, $this->orderBy)));
        return [...$this->arguments, ...$keys, ...($this->filter === null ? [] : [$this->filter])];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return strtoupper(implode('.', $this->function->name()->parts));
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->function, $this->arguments, $this->mode, $this->orderBy, $this->filter, $this->separator);
    }

}

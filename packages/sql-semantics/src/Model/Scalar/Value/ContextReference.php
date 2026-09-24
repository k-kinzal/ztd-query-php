<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * ContextReference has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Reading a clock precision
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT CURRENT_TIME(3)');
 *     $statement->outputs[0]->expression->precision // => 3
 */
final class ContextReference extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly ContextValueKind $request,
        public readonly ?int $precision,
    ) {
        if ($precision !== null && (!in_array($request, [ContextValueKind::CurrentTime, ContextValueKind::CurrentTimestamp, ContextValueKind::LocalTime, ContextValueKind::LocalTimestamp, ContextValueKind::UtcTime, ContextValueKind::UtcTimestamp, ContextValueKind::StatementTime], true) || $precision < 0)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only clock values accept a nonnegative precision.');
        }
        if (in_array($request, [ContextValueKind::UtcDate, ContextValueKind::UtcTime, ContextValueKind::UtcTimestamp, ContextValueKind::StatementTime], true) && $facts->type->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('UTC clock values and SYSDATE require MySQL.');
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
        return ExpressionKind::ContextReference;
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
        return $this->request->value;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->request, $this->precision);
    }

}

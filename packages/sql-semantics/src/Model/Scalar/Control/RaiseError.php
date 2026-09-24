<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Control;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests a SQLite trigger error with an unevaluated message expression.
 * @visibility public
 * @example Inspecting a trigger error and its message
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $trigger = (new \SqlSemantics\Binder($schema))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(FAIL, 'stop'); END");
 *     $raise = $trigger->body->steps[0]->outputs[0]->expression;
 *     $raise->spelling() // => 'RAISE FAIL'
 *     $raise->message->spelling() // => "'stop'"
 */
final class RaiseError extends Expression
{
    /**
     * @throws InvalidStructure
     * @visibility SqlSemantics
     */
    public function __construct(
        ExpressionFacts $facts,
        Node|Token $source,
        public readonly RaiseAction $action,
        public readonly Expression $message,
    ) {
        parent::__construct($facts, $source);
        if ($facts->type->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('RAISE expressions belong to SQLite triggers.');
        }
        if ($message->type->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('A trigger message must use the trigger dialect.');
        }
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Raise;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->message];
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function spelling(): string
    {
        return 'RAISE ' . $this->action->value;
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->action, $this->message);
    }
}

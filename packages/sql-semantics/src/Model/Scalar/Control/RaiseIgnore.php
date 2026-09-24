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
 * Abandons the current SQLite trigger action without producing a value.
 * @visibility public
 * @example Inspecting a trigger that abandons its action
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $trigger = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
 *     $raise = $trigger->body->steps[0]->outputs[0]->expression;
 *     $raise->spelling() // => 'RAISE IGNORE'
 *     count($raise->inputs()) // => 0
 */
final class RaiseIgnore extends Expression
{
    /**
     * @throws InvalidStructure
     * @visibility SqlSemantics
     */
    public function __construct(
        ExpressionFacts $facts,
        Node|Token $source,
    ) {
        parent::__construct($facts, $source);
        if ($facts->type->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('RAISE expressions belong to SQLite triggers.');
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
        return [];
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function spelling(): string
    {
        return 'RAISE IGNORE';
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source);
    }
}

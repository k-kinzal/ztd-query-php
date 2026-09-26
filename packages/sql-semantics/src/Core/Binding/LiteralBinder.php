<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Interprets literal categories from lexer terminals, preserving their SQL spelling.
 *
 * @visibility SqlSemantics
 */
final class LiteralBinder
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Classifies a literal or parameter terminal without evaluating SQL.
     */
    public function bind(Token $token): ?Expression
    {
        if (in_array($token->name, $this->dialect->platform()->syntax()->nodes('parameterToken'), true)) {
            return new Expression(ExpressionKind::Parameter, new TypeDescriptor($this->dialect, Builtin::Unknown), Nullability::Unknown, $token, symbol: $token->text);
        }
        $type = $this->literal($token);
        if ($type === null) {
            return null;
        }

        return new Expression(ExpressionKind::Literal, $type, strtoupper($token->text) === 'NULL' ? Nullability::AlwaysNull : Nullability::NotNull, $token, symbol: $token->text);
    }

    /**
     * Types a literal terminal without converting its contents, or returns null for a non-literal.
     */
    public function literal(Token $token): ?TypeDescriptor
    {
        return $this->dialect->platform()->types()->literal($token);
    }

    /**
     * Chooses an integer type using the supplied scalar policy.
     */
    public function integer(string $text): Builtin
    {
        return $this->dialect->platform()->types()->integer($text);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
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
        $name = $token->name;
        $text = strtoupper($token->text);
        if (in_array($name, $this->dialect->platform()->syntax()->nodes('parameterToken'), true)) {
            return new Expression(ExpressionKind::Parameter, new TypeDescriptor($this->dialect, 'unknown'), Nullability::Unknown, $token, symbol: $token->text);
        }
        $type = $this->typeName($token);
        if ($type === null) {
            return null;
        }

        return new Expression(ExpressionKind::Literal, new TypeDescriptor($this->dialect, $type), $text === 'NULL' ? Nullability::AlwaysNull : Nullability::NotNull, $token, symbol: $token->text);
    }

    /**
     * Classifies a literal's lexical category without converting its contents.
     */
    public function typeName(Token $token): ?string
    {
        return $this->dialect->platform()->types()->typeName($token);
    }

    /**
     * Chooses an integer type using the supplied scalar policy.
     */
    public function integer(string $text): string
    {
        return $this->dialect->platform()->types()->integer($text);
    }
}

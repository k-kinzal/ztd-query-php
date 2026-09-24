<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds MySQL literals written as several tokens: a character set introducer and adjacent string parts.
 * @visibility SqlSemantics
 */
final class TextLiteralBinder
{
    /**
     * Returns the introduced or joined literal; single-token literals are left to the literal binder.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $tokens = array_values(array_filter($source->tokens(), static fn (Token $token): bool => $token->text !== ''));
        if ($scope->identifiers->dialect !== Dialect::MySql || !in_array($source->name, ['text_literal', 'literal'], true) || count($tokens) < 2) {
            return null;
        }
        $introducer = $tokens[0]->name === 'UNDERSCORE_CHARSET' ? strtolower(substr($tokens[0]->text, 1)) : null;
        $parts = $introducer === null ? $tokens : array_slice($tokens, 1);
        $literal = count($parts) === 1 ? (new LiteralBinder(Dialect::MySql))->bind($parts[0]) : self::joined($source, $parts);
        if (!$literal instanceof Literal) {
            return null;
        }
        return $introducer === null ? $literal : new IntroducedLiteral($source, $introducer, $literal);
    }

    /**
     * Joins adjacent string parts into the one string literal MySQL reads them as.
     * @param non-empty-list<Token> $parts
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function joined(Node $source, array $parts): Literal
    {
        $national = strtoupper($parts[0]->text[0]) === 'N';
        $content = '';
        foreach ($parts as $index => $part) {
            $text = $index === 0 && $national ? substr($part->text, 1) : $part->text;
            $content .= self::content($text);
        }
        $text = ($national ? 'N' : '') . "'" . $content . "'";
        return new Literal(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'text'), Nullability::NotNull), $source, LiteralKind::Text, $text);
    }

    /**
     * Returns a quoted string's content in single-quote form, keeping its escape sequences.
     */
    public static function content(string $quoted): string
    {
        $inner = substr($quoted, 1, -1);
        if ($quoted[0] !== '"') {
            return $inner;
        }
        return (string) preg_replace('/(?<!\\\\)((?:\\\\\\\\)*)\'/', "$1''", str_replace('""', '"', $inner));
    }
}

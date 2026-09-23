<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Separates a type's outer spelling from literal-only parameter groups.
 * @visibility SqlSemantics
 */
final class TypeTokens
{
    /**
     * @return list<Token> Type keywords and encoding operands outside parameter parentheses
     */
    public static function outer(Node $source): array
    {
        $result = [];
        $depth = 0;
        foreach ($source->tokens() as $token) {
            if ($token->text === '(') {
                ++$depth;
            } elseif ($token->text === ')') {
                --$depth;
            } elseif ($depth === 0) {
                $result[] = $token;
            }
        }
        return $result;
    }

    /**
     * @return list<NumericParameter> Numeric operands in literal-only type syntax
     */
    public static function numbers(Node $source): array
    {
        $tokens = $source->tokens();
        if (in_array(strtoupper($tokens[0]->text), ['ENUM', 'SET'], true)) {
            return [];
        }
        $result = [];
        $depth = 0;
        $number = '';
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                ++$depth;
            } elseif ($token->text === ')') {
                if ($depth === 1 && $number !== '') {
                    $result[] = new NumericParameter($number);
                    $number = '';
                }
                --$depth;
            } elseif ($depth > 0 && $token->text === ',') {
                $result[] = new NumericParameter($number);
                $number = '';
            } elseif ($depth > 0) {
                $number .= $token->text;
            }
        }
        return $result;
    }
}

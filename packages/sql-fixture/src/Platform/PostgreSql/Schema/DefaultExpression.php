<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\NumericLiteral;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Interprets a DEFAULT expression, keeping anything but a constant as SQL text.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Returns the constant the expression denotes, dropping a type cast, or the expression text.
     */
    public function evaluate(Node $expression, string $sql): int|float|bool|string|null
    {
        $tokens = $expression->tokens();
        $sign = '';
        $first = $tokens[0] ?? null;
        if ($first !== null && ($first->text === '-' || $first->text === '+')) {
            $sign = $first->text;
            array_shift($tokens);
        }
        $constant = array_shift($tokens);
        if ($constant === null || ($tokens !== [] && !$this->isCast($expression, $tokens))) {
            return $expression->text($sql);
        }

        return match ($constant->name) {
            'ICONST', 'FCONST' => (new NumericLiteral())->decode($sign . $constant->text),
            'SCONST' => (new StringLiteral())->decode($constant),
            'TRUE_P' => true,
            'FALSE_P' => false,
            'NULL_P' => null,
            default => $expression->text($sql),
        };
    }

    /**
     * Reports whether the tokens after a constant are exactly one type cast.
     *
     * @param list<Token> $rest
     */
    public function isCast(Node $expression, array $rest): bool
    {
        $typenames = $expression->find('Typename');
        $typename = end($typenames);
        $cast = $rest[0] ?? null;
        if ($typename === false || $cast === null || !$cast->is('TYPECAST')) {
            return false;
        }
        $typeTokens = $typename->tokens();
        $firstTypeToken = $typeTokens[0] ?? null;
        $afterCast = $rest[1] ?? null;

        return count($typeTokens) === count($rest) - 1 && $firstTypeToken !== null && $afterCast !== null && $firstTypeToken->offset === $afterCast->offset;
    }

    /**
     * Reports whether the expression is a call to nextval.
     */
    public function isSequenceCall(Node $expression): bool
    {
        $call = $expression->find('func_application')[0] ?? null;
        $name = $call?->find('func_name')[0] ?? null;

        return $name !== null && strtolower(implode('', array_map(static fn (Token $token): string => $token->text, $name->tokens()))) === 'nextval';
    }
}

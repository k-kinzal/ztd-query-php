<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Editing;

use SqlParser\Lexer\Token;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Traversal\Expressions;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Builds source-preserving edits at an owned expression boundary before complete rebinding.
 *
 * @visibility SqlSemantics
 */
final class ExpressionEdit
{
    /**
     * Rejects fragments that escape a single parenthesized expression or target another tree.
     *
     * @throws InvalidStructure
     */
    public function sql(BoundStatement $statement, Expression $target, string $replacement, DialectParser $parser): string
    {
        if (!in_array($target, Expressions::all($statement), true)) {
            throw new InvalidStructure('The expression does not belong to this statement.');
        }
        $fragment = "SELECT (\n" . $replacement . "\n)";
        $parsed = $parser->parse($fragment);
        $tokens = array_values(array_filter($parsed->tokens(), static fn (Token $token): bool => $token->text !== ''));
        $this->boundary($tokens);
        $source = $target->source;
        $span = $source instanceof Token ? [$source->offset, $source->end()] : $source->span();
        $owned = $statement->source->tokens();
        $targetTokens = $source instanceof Token ? [$source] : $source->tokens();
        if ($span === null || $owned === [] || $targetTokens === []) {
            throw new InvalidStructure('An editable expression must have source tokens.');
        }
        foreach ($targetTokens as $token) {
            if (!in_array($token, $owned, true)) {
                throw new InvalidStructure('The expression source does not belong to this statement.');
            }
        }
        $base = $owned[0]->offset - strlen($owned[0]->leading);
        $sql = $statement->toString();
        $value = "(\n" . $replacement . "\n)";
        foreach ($statement->settings as $setting) {
            if (in_array($target, $setting->values, true)) {
                $value = "\n" . $replacement . "\n";
            }
        }
        return substr($sql, 0, $span[0] - $base) . $value . substr($sql, $span[1] - $base);
    }

    /**
     * Checks parsed tokens, so quoted delimiters and comments cannot escape the replacement.
     *
     * @param list<Token> $tokens
     * @throws InvalidStructure
     */
    public function boundary(array $tokens): void
    {
        $depth = 0;
        foreach (array_slice($tokens, 1) as $index => $token) {
            $depth += $token->text === '(' ? 1 : ($token->text === ')' ? -1 : 0);
            if ($depth <= 0 && $index !== count($tokens) - 2) {
                throw new InvalidStructure('Replacement SQL must be exactly one expression.');
            }
        }
        if ($depth !== 0 || ($tokens[1]->text ?? '') !== '(' || ($tokens[count($tokens) - 1]->text ?? '') !== ')') {
            throw new InvalidStructure('Replacement SQL must be exactly one expression.');
        }
    }
}

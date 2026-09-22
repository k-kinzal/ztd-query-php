<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Reads setting values at parsed token boundaries, never as column references.
 *
 * @visibility SqlSemantics
 */
final class SettingTokens
{
    /**
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    public static function split(array $tokens): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token->text === ',' && $depth === 0) {
                $groups[] = $current;
                $current = [];
            } else {
                $current[] = $token;
                $depth += in_array($token->text, ['(', '['], true) ? 1 : (in_array($token->text, [')', ']'], true) ? -1 : 0);
            }
        }
        if ($current !== []) {
            $groups[] = $current;
        }
        return $groups;
    }

    /**
     * @param non-empty-list<Token> $tokens
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function value(array $tokens, Node $source, Scope $scope): Expression
    {
        $first = $tokens[0];
        $last = $tokens[count($tokens) - 1];
        foreach (Tree::outer($source, ['expr', 'a_expr', 'signed', 'minus_num', 'plus_num']) as $expression) {
            if ($expression->span() === [$first->offset, $last->end()]) {
                return (new ExpressionBinder())->bind($expression, $scope);
            }
        }
        if (count($tokens) === 1) {
            $literal = (new \SqlSemantics\Binding\LiteralBinder($scope->identifiers->dialect))->bind($first);
            if ($literal !== null) {
                return $literal;
            }
            if (strtoupper($first->text) === 'DEFAULT' && !in_array($first->name, ['IDENT', 'IDENT_QUOTED', 'ID', 'SCONST', 'TEXT_STRING', 'STRING'], true)) {
                return (new ExpressionBinder())->token($first, $scope);
            }
        }
        $node = new Node('configuration_value', 0, $tokens);
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'text'), Nullability::NotNull);
        $keyword = \SqlSemantics\Model\Scalar\Value\SettingKeyword::tryFrom(implode(' ', self::words($tokens)));
        if ($keyword !== null) {
            return new \SqlSemantics\Model\Scalar\Value\ConfigurationKeyword($facts, $node, $keyword);
        }
        if (count($tokens) === 1) {
            return new \SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier($facts, $node, [$scope->identifiers->name($tokens[0])]);
        }
        throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unclassified configuration value: ' . $node->toString());
    }

    /**
     * @param list<Token> $tokens
     * @return list<string>
     */
    public static function words(array $tokens): array
    {
        return array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
    }
}

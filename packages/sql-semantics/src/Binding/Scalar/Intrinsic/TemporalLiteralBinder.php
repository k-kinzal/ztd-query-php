<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;

/**
 * Binds MySQL temporal constants and ODBC escapes `{ident expr}`.
 * @visibility SqlSemantics
 */
final class TemporalLiteralBinder
{
    /**
     * An ODBC escape is a temporal constant for `d`, `t` or `ts` with a string, and otherwise its inner expression.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        if ($scope->identifiers->dialect !== Dialect::MySql) {
            return null;
        }
        $children = Tree::significant($source);
        if ($source->name === 'temporal_literal' && count($children) === 2) {
            $text = (new ExpressionBinder())->bind($children[1], $scope);
            $category = self::category(Tree::text($children[0]));
            return $text instanceof Literal && $category !== null ? new TemporalLiteral($source, $category, $text) : null;
        }
        if (count($children) !== 4 || !$children[0] instanceof Token || $children[0]->text !== '{' || !$children[1] instanceof Node || !$children[2] instanceof Node) {
            return null;
        }
        $inner = (new ExpressionBinder())->bind($children[2], $scope);
        $category = self::category(match (strtolower(Tree::text($children[1]))) {
            'd' => 'DATE',
            't' => 'TIME',
            'ts' => 'TIMESTAMP',
            default => '',
        });
        return $inner instanceof Literal && $inner->literalKind === LiteralKind::Text && $category !== null ? new TemporalLiteral($source, $category, $inner) : $inner;
    }

    /**
     * Classifies the temporal keyword.
     */
    public static function category(string $keyword): ?LiteralKind
    {
        return match (strtoupper($keyword)) {
            'DATE' => LiteralKind::Date,
            'TIME' => LiteralKind::Time,
            'TIMESTAMP' => LiteralKind::Timestamp,
            default => null,
        };
    }
}

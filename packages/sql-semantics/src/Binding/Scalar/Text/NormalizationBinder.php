<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Text;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Text\Normalization;
use SqlSemantics\Model\Scalar\Text\NormalizedPredicate;
use SqlSemantics\Model\Scalar\Text\UnicodeNormalForm;

/**
 * Retains the string and Unicode normal form of PostgreSQL's NORMALIZE and IS [NOT] NORMALIZED.
 * @visibility SqlSemantics
 */
final class NormalizationBinder
{
    /**
     * Recognizes the NORMALIZE keyword form without intercepting a quoted function call.
     */
    public static function bind(Node $source, Scope $scope): ?Normalization
    {
        $first = $source->children[0] ?? null;
        $string = Tree::child($source, ['a_expr']);
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || $source->name !== 'func_expr_common_subexpr' || !$first instanceof Token || $first->name !== 'NORMALIZE' || $string === null) {
            return null;
        }
        return new Normalization($source, (new ExpressionBinder())->bind($string, $scope), self::form($source));
    }

    /**
     * Recognizes `string IS [NOT] [form] NORMALIZED`.
     */
    public static function test(Node $source, Scope $scope): ?NormalizedPredicate
    {
        $last = $source->children[count($source->children) - 1] ?? null;
        $string = $source->children[0] ?? null;
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || !$last instanceof Token || $last->name !== 'NORMALIZED' || !$string instanceof Node) {
            return null;
        }
        $negated = in_array('NOT', array_map(static fn (Node|Token $child): string => $child instanceof Token ? $child->name : '', $source->children), true);
        return new NormalizedPredicate($source, (new ExpressionBinder())->bind($string, $scope), self::form($source), $negated);
    }

    /**
     * Reads the written normal form, NFC when none is written.
     */
    public static function form(Node $source): UnicodeNormalForm
    {
        $form = Tree::child($source, ['unicode_normal_form']);
        return $form === null ? UnicodeNormalForm::Nfc : UnicodeNormalForm::from(strtoupper(Tree::text($form)));
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\TableFunction\Json;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies SQL/JSON options into their operation-specific domains.
 * @visibility SqlSemantics
 */
final class JsonOptions
{
    /**
     * Binds a document expression separately from its declared encoding.
     */
    public static function input(Node $node, Scope $scope): Json\Input
    {
        $expression = Tree::child($node, ['a_expr', 'expr']) ?? $node;
        $format = Tree::outer($node, ['json_format_clause'])[0] ?? null;
        return new Json\Input((new ExpressionBinder())->bind($expression, $scope), self::format($format));
    }

    /**
     * @throws InvalidSql
     */
    public static function format(?Node $node): ?Json\Format
    {
        if ($node === null || !Tree::hasTokens($node)) {
            return null;
        }
        return Json\Format::tryFrom(strtoupper(Tree::text($node))) ?? throw new InvalidSql(InputViolation::JsonOption, $node);
    }

    /**
     * Normalizes optional ARRAY noise words while retaining the wrapper policy.
     */
    public static function wrapper(?Node $node): Json\ArrayWrapping
    {
        $text = $node === null ? '' : str_replace(' ARRAY', '', strtoupper(Tree::text($node)));
        return Json\ArrayWrapping::from($text === 'WITH WRAPPER' ? 'WITH UNCONDITIONAL WRAPPER' : $text);
    }

    /**
     * Reads whether scalar-string quotation is kept or omitted.
     */
    public static function quotes(?Node $node): Json\Quotes
    {
        $text = $node === null ? '' : strtoupper(Tree::text($node));
        return Json\Quotes::from(str_replace(' ON SCALAR STRING', '', $text));
    }

    /**
     * @return array{?Node, ?Node} ON EMPTY and ON ERROR responses
     */
    public static function responses(Node $source): array
    {
        $container = Tree::child($source, ['json_behavior_clause_opt', 'json_on_error_clause_opt', 'opt_on_empty_or_error_json_table']);
        if ($container === null) {
            return [null, null];
        }
        if ($container->name === 'opt_on_empty_or_error_json_table') {
            return [Tree::outer($container, ['on_empty'])[0] ?? null, Tree::outer($container, ['on_error'])[0] ?? null];
        }
        $result = [null, null];
        $children = Tree::significant($container);
        foreach ($children as $index => $child) {
            if ($child instanceof Node && $child->name === 'json_behavior') {
                $kind = strtoupper(Tree::text($children[$index + 2]));
                $result[$kind === 'EMPTY' ? 0 : 1] = $child;
            }
        }
        return $result;
    }

    /**
     * @throws InvalidSql
     */
    public static function response(?Node $node, Scope $scope): Json\Response\ValueResponse
    {
        if ($node === null) {
            return Json\Response\ValueBehavior::Default;
        }
        $value = Tree::outer($node, ['a_expr', 'signed_literal', 'text_literal'])[0] ?? null;
        if ($value !== null) {
            return new Json\Response\DefaultResponse((new ExpressionBinder())->bind($value, $scope));
        }
        $text = str_replace([' ON EMPTY', ' ON ERROR'], '', strtoupper(Tree::text($node)));
        return Json\Response\ValueBehavior::tryFrom($text === 'EMPTY' ? 'EMPTY ARRAY' : $text) ?? throw new InvalidSql(InputViolation::JsonOption, $node);
    }

    /**
     * @throws InvalidSql
     */
    public static function exists(?Node $node): Json\Response\ExistsResponse
    {
        if ($node === null) {
            return Json\Response\ExistsResponse::Default;
        }
        $text = str_replace(' ON ERROR', '', strtoupper(Tree::text($node)));
        return Json\Response\ExistsResponse::tryFrom($text) ?? throw new InvalidSql(InputViolation::JsonOption, $node);
    }

    /**
     * @throws InvalidSql
     */
    public static function tableError(?Node $node): Json\Response\TableError
    {
        if ($node === null) {
            return Json\Response\TableError::Default;
        }
        return Json\Response\TableError::tryFrom(strtoupper(Tree::text($node))) ?? throw new InvalidSql(InputViolation::JsonOption, $node);
    }
}

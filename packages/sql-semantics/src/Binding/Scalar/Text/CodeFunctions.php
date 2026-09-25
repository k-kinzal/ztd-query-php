<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Text;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text;

/**
 * Binds the MySQL functions whose operands are more than expressions: `CHAR(... USING charset)` and the forms of
 * WEIGHT_STRING.
 * @visibility SqlSemantics
 */
final class CodeFunctions
{
    /**
     * Returns the bound function, or null when the node is neither CHAR(...) nor WEIGHT_STRING(...).
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $first = $source->children[0] ?? null;
        if ($scope->identifiers->dialect !== Dialect::MySql || !$first instanceof Token || !in_array($source->name, ['function_call_keyword', 'function_call_conflict'], true)) {
            return null;
        }
        return match (strtoupper($first->text)) {
            'CHAR' => self::codes($source, $scope),
            'WEIGHT_STRING' => self::weights($source, $scope),
            default => null,
        };
    }

    /**
     * Binds `CHAR(code, ... [USING charset])`.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function codes(Node $source, Scope $scope): ?Text\CharacterCodes
    {
        $list = Tree::child($source, ['expr_list']);
        if ($list === null) {
            return null;
        }
        $charset = Tree::child($source, ['charset_name']);
        $codes = array_map(static fn (Node $code): Expression => (new ExpressionBinder())->bind($code, $scope), Tree::outer($list, ['expr']));
        return new Text\CharacterCodes($source, $codes, $charset === null ? null : strtolower(trim(Tree::text($charset), "`'\"")));
    }

    /**
     * Binds WEIGHT_STRING with its padding and MySQL 5.x levels, or its internal numeric form.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function weights(Node $source, Scope $scope): ?Expression
    {
        $operand = Tree::child($source, ['expr']);
        if ($operand === null) {
            return null;
        }
        $value = (new ExpressionBinder())->bind($operand, $scope);
        $numbers = array_map(self::number(...), array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && $child->name === 'ulong_num')));
        if (count($numbers) === 3) {
            return new Text\InternalWeightString($source, $value, $numbers[0], $numbers[1], $numbers[2]);
        }
        $length = Tree::child($source, ['ws_nweights', 'ws_num_codepoints']);
        $words = array_map(static fn (Node|Token $child): string => $child instanceof Token ? strtoupper($child->text) : '', $source->children);
        $padding = $length === null ? null : new Text\WeightPadding(in_array('BINARY', $words, true), self::number(Tree::outer($length, ['real_ulong_num'])[0] ?? $length));
        $levels = Tree::child($source, ['opt_ws_levels']);
        return new Text\WeightString($source, $value, $padding, $levels === null ? [] : self::levels($levels));
    }

    /**
     * Reads a LEVEL clause; MySQL clamps each level number to the range 1 to 6.
     *
     * @return list<Text\WeightLevel>
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function levels(Node $clause): array
    {
        $level = static fn (Node $node): int => max(1, min(6, self::number($node)));
        $range = Tree::outer($clause, ['ws_level_range'])[0] ?? null;
        if ($range !== null) {
            $bounds = array_map($level, Tree::outer($range, ['ws_level_number']));
            return [new Text\WeightLevel($bounds[0] ?? 1, max($bounds[0] ?? 1, $bounds[1] ?? 1))];
        }
        return array_map(static function (Node $item) use ($level): Text\WeightLevel {
            $number = $level(Tree::outer($item, ['ws_level_number'])[0] ?? $item);
            $flags = Tree::child($item, ['ws_level_flags']);
            $words = $flags === null ? [] : array_map(strtoupper(...), array_map(static fn (Token $token): string => $token->text, $flags->tokens()));
            return new Text\WeightLevel($number, $number, in_array('DESC', $words, true), in_array('REVERSE', $words, true));
        }, Tree::outer($clause, ['ws_level_list_item']));
    }

    /**
     * Reads an unsigned number written in decimal or hexadecimal.
     */
    public static function number(Node $node): int
    {
        $text = strtolower(Tree::text($node));
        return str_starts_with($text, '0x') ? (int) hexdec(substr($text, 2)) : (int) $text;
    }
}

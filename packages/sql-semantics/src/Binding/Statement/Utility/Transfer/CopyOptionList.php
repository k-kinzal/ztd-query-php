<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Transfer;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\OptionWords;

/**
 * Reads COPY options from the legacy keyword list and the parenthesized list into the name and argument pairs the server's option reader sees.
 * An argument is decoded text or an integer, a list of names, true for an asterisk, or null when omitted.
 * @visibility SqlSemantics
 */
final class CopyOptionList
{
    /**
     * @return list<array{string, string|int|list<string>|true|null, Node}> Options in the server's order: BINARY, USING DELIMITERS, then the option list
     * @throws UnclassifiedSql
     */
    public static function read(Node $source, Identifiers $identifiers): array
    {
        $options = [];
        $binary = Tree::child($source, ['opt_binary']);
        if ($binary !== null && $binary->tokens() !== []) {
            $options[] = ['format', 'binary', $binary];
        }
        $delimiter = Tree::child($source, ['copy_delimiter']);
        if ($delimiter !== null && $delimiter->tokens() !== []) {
            $options[] = ['delimiter', self::text($delimiter, $identifiers), $delimiter];
        }
        foreach (Tree::outer($source, ['copy_opt_item', 'copy_generic_opt_elem']) as $item) {
            $options[] = $item->name === 'copy_opt_item' ? self::legacy($item, $identifiers) : self::generic($item, $identifiers);
        }
        return $options;
    }

    /**
     * Reads one keyword option such as CSV, HEADER, NULL AS 'x' or FORCE QUOTE *.
     * @return array{string, string|int|list<string>|true|null, Node}
     * @throws UnclassifiedSql
     */
    public static function legacy(Node $item, Identifiers $identifiers): array
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $item->tokens());
        $columns = Tree::child($item, ['columnList']);
        $choice = $columns === null ? true : self::names($columns, $identifiers);
        return match ($words[0] ?? '') {
            'BINARY' => ['format', 'binary', $item],
            'CSV' => ['format', 'csv', $item],
            'FREEZE' => ['freeze', null, $item],
            'HEADER' => ['header', null, $item],
            'DELIMITER', 'NULL', 'QUOTE', 'ESCAPE', 'ENCODING' => [strtolower($words[0]), self::text($item, $identifiers), $item],
            'FORCE' => [match ($words[1] ?? '') {
                'QUOTE' => 'force_quote', 'NOT' => 'force_not_null', default => 'force_null'
            }, $choice, $item],
            default => throw new UnclassifiedSql('Unclassified COPY option: ' . Tree::text($item)),
        };
    }

    /**
     * Reads one parenthesized option with its folded name.
     * @return array{string, string|int|list<string>|true|null, Node}
     * @throws UnclassifiedSql
     */
    public static function generic(Node $item, Identifiers $identifiers): array
    {
        $name = $identifiers->name($item->tokens()[0] ?? throw new UnclassifiedSql('A COPY option requires its name.'));
        $argument = Tree::child($item, ['copy_generic_opt_arg']);
        $tokens = $argument?->tokens() ?? [];
        $list = $argument === null ? null : Tree::child($argument, ['copy_generic_opt_arg_list']);
        if ($argument === null || $tokens === []) {
            return [$name, null, $item];
        }
        $value = match (true) {
            $list !== null => array_map(static fn (Node $element): string => (string) OptionWords::raw($element, $identifiers), Tree::outer($list, ['copy_generic_opt_arg_list_item'])),
            $tokens[0]->text === '*' => true,
            $tokens[0]->name === 'DEFAULT' => 'default',
            default => OptionWords::raw($argument, $identifiers),
        };
        return [$name, $value, $item];
    }

    /**
     * Decodes the string constant of a keyword option.
     * @throws UnclassifiedSql
     */
    public static function text(Node $item, Identifiers $identifiers): string
    {
        $constant = Tree::outer($item, ['Sconst'])[0] ?? throw new UnclassifiedSql('A COPY option requires its string constant.');
        return OptionWords::word($constant->tokens()[0] ?? throw new UnclassifiedSql('A string constant requires its token.'), $identifiers);
    }

    /**
     * @return list<string>
     * @throws UnclassifiedSql
     */
    public static function names(Node $columns, Identifiers $identifiers): array
    {
        return array_map(static fn (Node $column): string => $identifiers->name($column->tokens()[0] ?? throw new UnclassifiedSql('A column requires its name.')), Tree::outer($columns, ['columnElem']));
    }
}

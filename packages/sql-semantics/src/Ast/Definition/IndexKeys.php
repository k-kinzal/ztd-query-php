<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\IndexElement;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;

/**
 * Reads ordered index keys without flattening nested expressions into column names.
 *
 * @visibility SqlSemantics
 */
final class IndexKeys
{
    /**
     * @return list<IndexElement>
     */
    public static function read(Node $source, Identifiers $identifiers): array
    {
        $list = array_values(array_filter(Tree::outer($source, ['index_params', 'key_list_with_expression', 'key_list', 'sortlist', 'columnList']), Tree::hasTokens(...)))[0] ?? null;
        if ($list === null) {
            return [];
        }
        $nodes = Tree::outer($list, ['index_elem', 'key_part_with_expression', 'key_part', 'columnElem']);
        if ($list->name === 'sortlist') {
            while ($list !== null) {
                $nodes[] = new Node('index_element', 0, array_values(array_filter($list->children, static fn ($child): bool => $child instanceof Node ? $child->name !== 'sortlist' : $child->text !== ',')));
                $list = Tree::child($list, ['sortlist']);
            }
            $nodes = array_reverse($nodes);
        }
        return array_map(static fn (Node $node): IndexElement => self::element($node, $identifiers), $nodes);
    }

    /**

     * Reads the value and modifiers of one key.

     */
    public static function element(Node $source, Identifiers $identifiers): IndexElement
    {
        $key = Tree::child($source, ['key_part']) ?? $source;
        [$column, $expression, $collation] = self::value($key, $identifiers);
        $operator = array_values(array_filter(Tree::outer($key, ['opt_qualified_name']), Tree::hasTokens(...)))[0] ?? null;
        $direction = array_values(array_filter(Tree::outer($key, ['opt_asc_desc', 'opt_ordering_direction', 'sortorder']), Tree::hasTokens(...)))[0] ?? null;
        $nulls = array_values(array_filter(Tree::outer($key, ['opt_nulls_order', 'nulls']), Tree::hasTokens(...)))[0] ?? null;
        $tokens = $key->tokens();
        $prefix = $column !== null && ($tokens[1]->text ?? '') === '(' && isset($tokens[2]) && ctype_digit($tokens[2]->text) ? (int) $tokens[2]->text : null;
        return new IndexElement($column, $expression, $direction === null ? null : strtoupper(Tree::text($direction)), $nulls === null ? null : strtoupper($nulls->tokens()[count($nulls->tokens()) - 1]->text), $collation, $operator === null ? [] : $identifiers->parts($operator), $prefix, $source, OptionReader::read($key, $identifiers));
    }
    /**
     * @return array{?string, ?Node, list<string>}
     */
    public static function value(Node $key, Identifiers $identifiers): array
    {
        $expression = array_values(array_filter(Tree::outer($key, ['a_expr', 'expr', 'func_expr_windowless']), Tree::hasTokens(...)))[0] ?? null;
        $column = null;
        $collation = [];
        if ($expression !== null && count($expression->tokens()) === 3 && strtoupper($expression->tokens()[1]->text) === 'COLLATE') {
            $collation = [$identifiers->name($expression->tokens()[2])];
            $expression = Tree::child($expression, ['expr']) ?? $expression;
        }
        $name = Tree::child($key, ['ColId', 'ident', 'nm']);
        if ($name !== null && $expression === null) {
            $column = $identifiers->parts($name)[0];
        } elseif ($expression !== null && count($expression->tokens()) === 1 && in_array($expression->tokens()[0]->name, ['ID', 'IDENT', 'IDENT_QUOTED'], true)) {
            $column = $identifiers->name($expression->tokens()[0]);
            $expression = null;
        }
        $collate = array_values(array_filter(Tree::outer($key, ['opt_collate']), Tree::hasTokens(...)))[0] ?? null;
        if ($collate !== null) {
            $collation = array_slice($identifiers->parts($collate), 1);
        }
        return [$column, $expression, $collation];
    }

}

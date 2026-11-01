<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Session\Syntax;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;
use Throwable;

/**
 * Finds the table MySQL 5.6 and 5.7 refuse as named twice while they parse a statement (ER_NONUNIQ_TABLE), before the syntax error or the parameter marker they would refuse later.
 *
 * Two tables of a query block clash when they have the same alias, or name, in the same
 * database, the current one when none is written; a derived table has no database, and the
 * tables of a subquery or a derived table are those of another query block (verified on live
 * 5.6.51 and 5.7.44 servers).
 *
 * @visibility MySqlMemory
 */
final class Clashes
{
    /**
     * Answers the alias MySQL 5.6 refuses as named twice among the tables of a query block written before the first parameter marker of a statement that is not prepared, which it finds while it parses them, before the marker (ER_NONUNIQ_TABLE; verified on a live 5.6.51 server), with the offset of the marker; or null.
     *
     * @param string $database The current database
     * @return array{string, int}|null
     */
    public function listed(Node $tree, string $database): ?array
    {
        $markers = array_values(array_filter($tree->tokens(), static fn (Token $token): bool => $token->name === 'PARAM_MARKER'));
        if ($markers === []) {
            return null;
        }
        $offset = $markers[0]->offset;
        foreach ([...$tree->find('table_reference_list'), ...$tree->find('select_from'), ...$tree->find('join_table_list')] as $root) {
            $alias = $this->clash($this->factors($root, $database), $offset);
            if ($alias !== null) {
                return [$alias, $offset];
            }
        }

        return null;
    }

    /**
     * Answers the alias MySQL 5.6 and 5.7 refuse as named twice in a query block when they reduce a nested join before the syntax error a failure reports (ER_NONUNIQ_TABLE), with the offset of the end of that nested join; or null.
     *
     * The server checks the tables a query block has named so far when it reduces parentheses
     * around table references, which is before it refuses an alias, a union, an ORDER BY or a LIMIT
     * of the nested join (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param string $database The current database
     * @return array{string, int}|null
     */
    public function nested(Node $tree, Throwable $failure, string $database): ?array
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        $limit = $cause instanceof SyntaxException ? $cause->token->offset : PHP_INT_MAX;
        $clashes = [];
        foreach ([...$tree->find('table_reference_list'), ...$tree->find('select_from'), ...$tree->find('join_table_list')] as $root) {
            $factors = $this->factors($root, $database);
            foreach ($this->derived($root, true) as $list) {
                $tokens = $list->tokens();
                $end = $tokens === [] ? PHP_INT_MAX : $tokens[count($tokens) - 1]->offset;
                $alias = $end < $limit ? $this->clash($factors, $end) : null;
                if ($alias !== null) {
                    $clashes[$end] = $alias;
                }
            }
        }
        ksort($clashes);
        $alias = reset($clashes);

        return $alias === false ? null : [$alias, (int) key($clashes)];
    }

    /**
     * Answers the table a multi-table DELETE of MySQL 5.6 or 5.7 names twice among the tables it deletes from (ER_NONUNIQ_TABLE), with the offset of the end of the second name; or null.
     *
     * The server refuses the second name when it reads the list, which is before it refuses a
     * union, an ORDER BY or a LIMIT of a nested join among the tables it reads. Two names clash
     * when they name the same table, in the current database when none is written (verified on
     * live 5.6.51 and 5.7.44 servers).
     *
     * @param string $database The current database
     * @return array{string, int}|null
     */
    public function targets(Node $tree, Throwable $failure, string $database): ?array
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        if (!$cause instanceof SyntaxException) {
            return null;
        }
        $syntax = new Syntax();
        foreach ([...$tree->find('table_alias_ref_list'), ...$tree->find('table_wild_list')] as $list) {
            $seen = [];
            foreach ([...$list->find('table_ident_opt_wild'), ...$list->find('table_wild_one')] as $target) {
                $tokens = $target->tokens();
                $names = array_values(array_filter($tokens, static fn (Token $token): bool => !in_array($token->text, ['.', '*'], true)));
                $table = $syntax->identifier($names[count($names) - 1]->text ?? '');
                $key = (count($names) > 1 ? $syntax->identifier($names[0]->text) : $database) . "\0" . $table;
                if (isset($seen[$key])) {
                    return [$table, $tokens[count($tokens) - 1]->offset];
                }
                $seen[$key] = true;
            }
        }

        return null;
    }

    /**
     * Answers the alias of the first table that clashes with one before it among the tables written up to an offset, or null.
     *
     * @param list<array{string, string, int}> $factors The database, the alias and the offset of each table
     */
    public function clash(array $factors, int $end): ?string
    {
        $seen = [];
        foreach ($factors as [$schema, $alias, $offset]) {
            if ($offset > $end) {
                continue;
            }
            if (isset($seen[$schema . "\0" . $alias])) {
                return $alias;
            }
            $seen[$schema . "\0" . $alias] = true;
        }

        return null;
    }

    /**
     * Answers the table lists of parentheses around table references of a query block, `select_derived` nodes that hold no SELECT of their own, in the order the server reduces them: each after those inside it.
     *
     * @param bool $root Whether the node is the table references of the query block, rather than a node inside them
     * @return list<Node>
     */
    public function derived(Node $node, bool $root = false): array
    {
        if (!$root && in_array($node->name, ['subselect', 'table_reference_list', 'select_from'], true)) {
            return [];
        }
        $found = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                array_push($found, ...$this->derived($child));
            }
        }
        if ($node->name === 'select_derived' && $this->query($node) === null) {
            $found[] = $node;
        }

        return $found;
    }

    /**
     * Answers the table factor a `select_derived` node holds when it is a SELECT, the query of a derived table, or null when it holds table references.
     */
    public function query(Node $derived): ?Node
    {
        foreach ($derived->children as $child) {
            if (!$child instanceof Node || $child->name !== 'derived_table_list') {
                continue;
            }
            $references = $this->top($child);
            $factor = count($references) === 1 ? ($references[0]->find('table_factor')[0] ?? null) : null;
            foreach ($factor === null ? [] : $factor->children as $part) {
                if ($part instanceof Node && in_array($part->name, ['select_item_list', 'select_derived2'], true)) {
                    return $factor;
                }
            }
        }

        return null;
    }

    /**
     * Answers the references a table list holds at its own level.
     *
     * @return list<Node>
     */
    public function top(Node $list): array
    {
        $found = [];
        foreach ($list->children as $child) {
            if ($child instanceof Node && $child->name === 'derived_table_list') {
                array_push($found, ...$this->top($child));
            } elseif ($child instanceof Node && $child->name === 'esc_table_ref') {
                $found[] = $child;
            }
        }

        return $found;
    }

    /**
     * Answers the database and the alias of each table a node of table references holds, through nested joins, in written order; a subquery holds none.
     *
     * @param string $database The current database
     * @return list<array{string, string, int}> The database, the alias and the offset of each table
     */
    public function factors(Node $node, string $database): array
    {
        if ($node->name === 'subselect') {
            return [];
        }
        if ($node->name === 'table_factor') {
            return $this->factor($node, $database);
        }
        $found = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                array_push($found, ...$this->factors($child, $database));
            }
        }

        return $found;
    }

    /**
     * Answers the database and the alias of the tables a `table_factor` node holds: the table it names, a derived table, which has no database, or the tables of the nested join in its parentheses.
     *
     * @param string $database The current database
     * @return list<array{string, string, int}> The database, the alias and the offset of each table
     */
    public function factor(Node $factor, string $database): array
    {
        $parts = [];
        foreach ($factor->children as $child) {
            if ($child instanceof Node) {
                $parts[$child->name] = $child;
            }
        }
        $syntax = new Syntax();
        $words = array_values(array_filter(isset($parts['opt_table_alias']) ? $parts['opt_table_alias']->tokens() : [], static fn (Token $token): bool => strtoupper($token->text) !== 'AS'));
        $alias = $words === [] ? null : $syntax->identifier($words[count($words) - 1]->text);
        if (isset($parts['table_ident'])) {
            $names = array_values(array_filter($parts['table_ident']->tokens(), static fn (Token $token): bool => $token->text !== '.'));
            $table = $syntax->identifier($names[count($names) - 1]->text ?? '');
            $schema = count($names) > 1 ? $syntax->identifier($names[0]->text) : $database;

            return [[$schema, $alias ?? $table, $names[0]->offset ?? 0]];
        }
        $derived = isset($parts['select_derived_union']) ? ($parts['select_derived_union']->find('select_derived')[0] ?? null) : null;
        if ($derived !== null && $this->query($derived) !== null) {
            return $alias === null ? [] : [['', $alias, $factor->tokens()[0]->offset ?? 0]];
        }

        return $derived === null ? [] : $this->factors($derived, $database);
    }
}

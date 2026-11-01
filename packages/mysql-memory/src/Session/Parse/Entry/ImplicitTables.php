<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use SqlParser\Parser\Node;

/**
 * Finds table names that need the default database before a refused part of the statement.
 *
 * The server resolves the tables it has read before it checks a condition it finds after
 * reading: an unqualified table written before the refused part raises "No database
 * selected" first when no database is selected. Names of common table expressions and
 * SHOW targets written with their own FROM database need no default database, and an
 * empty name is refused before the database is looked at.
 *
 * @visibility MySqlMemory
 */
final class ImplicitTables
{
    /**
     * Tells whether an unqualified table name is written before the given offset.
     *
     * @param bool $legacy Whether the release is MySQL 5.6 or 5.7, which resolve a SHOW filter before the shown table's database
     */
    public function before(Node $tree, int $end, bool $legacy = false): bool
    {
        if ($legacy && ($tree->tokens()[0]->name ?? '') === 'SHOW') {
            return false;
        }
        $expressions = [];
        foreach ($tree->find('common_table_expr') as $expression) {
            $first = $expression->children[0] ?? null;
            if ($first instanceof Node) {
                $expressions[] = strtolower(implode('', array_map(static fn (\SqlParser\Lexer\Token $token): string => trim($token->text, '`"'), $first->tokens())));
            }
        }
        foreach ($this->tables($tree) as $table) {
            $span = $table->span();
            if ($span === null || $span[0] >= $end || count($table->children) === 3) {
                continue;
            }
            $name = strtolower(implode('', array_map(static fn (\SqlParser\Lexer\Token $token): string => trim($token->text, '`"'), $table->tokens())));
            if ($name !== '' && !in_array(ltrim($name, '.'), $expressions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collects the table name nodes whose database is not named elsewhere in the statement.
     *
     * @return list<Node>
     */
    public function tables(Node $tree): array
    {
        $qualified = false;
        foreach ($tree->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['opt_db', 'in_db'], true) && !$child->isEmpty()) {
                $qualified = true;
            }
        }
        $tables = [];
        foreach ($tree->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->name === 'table_ident') {
                if (!$qualified) {
                    $tables[] = $child;
                }
                continue;
            }
            array_push($tables, ...$this->tables($child));
        }

        return $tables;
    }
}

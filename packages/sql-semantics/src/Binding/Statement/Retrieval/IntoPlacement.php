<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Retrieval;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Decides whether an INTO clause stands where the server accepts it: on the first SELECT of a PostgreSQL statement, or after the last query block of a MySQL statement, never inside a subquery or a WITH declaration.
 * @visibility SqlSemantics
 */
final class IntoPlacement
{
    private const MYSQL_BOUNDARIES = ['subquery', 'subselect', 'table_subquery', 'derived_table', 'table_factor', 'with_clause'];

    /**
     * Returns the INTO clauses written anywhere in the statement.
     *
     * @return list<Node>
     */
    public static function clauses(Node $statement): array
    {
        return array_values(array_filter(Tree::outer($statement, ['into_clause', 'into']), Tree::hasTokens(...)));
    }

    /**
     * Whether a PostgreSQL INTO clause belongs to the leftmost SELECT of the outermost query.
     */
    public static function first(Node $statement, Node $into): bool
    {
        $leaf = self::leftmost(Tree::outer($statement, ['SelectStmt'])[0] ?? $statement);
        return $leaf !== null && Tree::child($leaf, ['into_clause']) === $into;
    }

    /**
     * Returns the leftmost simple SELECT reached without entering WITH declarations.
     */
    public static function leftmost(Node $node): ?Node
    {
        if ($node->name === 'simple_select') {
            $first = Tree::significant($node)[0] ?? null;
            return $first instanceof Node && $first->name === 'select_clause' ? self::leftmost($first) : $node;
        }
        foreach (Tree::significant($node) as $child) {
            if ($child instanceof Node && in_array($child->name, ['SelectStmt', 'select_no_parens', 'select_with_parens', 'select_clause', 'simple_select'], true)) {
                return self::leftmost($child);
            }
        }
        return null;
    }

    /**
     * Whether a MySQL INTO clause stands outside subqueries with no set operation following it.
     */
    public static function last(Node $statement, Node $into): bool
    {
        $tokens = self::tokens($statement);
        $start = array_search($into->tokens()[0] ?? null, $tokens, true);
        if ($start === false) {
            return false;
        }
        foreach (array_slice($tokens, $start) as $token) {
            if (in_array(strtoupper($token->text), ['UNION', 'EXCEPT', 'INTERSECT'], true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the statement tokens outside subqueries, derived tables and WITH declarations.
     *
     * @return list<Token>
     */
    public static function tokens(Node $node): array
    {
        $tokens = [];
        foreach ($node->children as $child) {
            if ($child instanceof Token) {
                $tokens[] = $child;
            } elseif (!in_array($child->name, self::MYSQL_BOUNDARIES, true)) {
                array_push($tokens, ...self::tokens($child));
            }
        }
        return $tokens;
    }
}

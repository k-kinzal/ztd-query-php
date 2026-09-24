<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Conditional;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scalar\QueryExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;

/**
 * Binds SQLite membership in a named relation, `x [NOT] IN [schema.]table` or `x [NOT] IN function(arguments)`,
 * which SQLite defines as membership in `SELECT * FROM` that relation.
 * @visibility SqlSemantics
 */
final class TableMembership
{
    /**
     * Returns the membership as a subquery over the named relation, or null for another production.
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     * @throws \SqlParser\Parser\SyntaxException
     * @throws \SqlParser\Lexer\LexicalException
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $operator = Tree::child($source, ['in_op']);
        $relation = Tree::child($source, ['nm']);
        $value = $source->children[0] ?? null;
        if ($scope->identifiers->dialect !== Dialect::Sqlite || $operator === null || $relation === null || !$value instanceof Node || $scope->queries === null) {
            return null;
        }
        $start = $relation->tokens()[0]->offset ?? 0;
        $written = array_values(array_filter($source->tokens(), static fn (Token $token): bool => $token->offset >= $start));
        $text = implode('', array_map(static fn (Token $token): string => $token->toString(), $written));
        $select = Tree::outer((new DialectParser(Dialect::Sqlite))->parse('SELECT * FROM ' . $text), ['select'])[0] ?? null;
        if ($select === null) {
            return null;
        }
        $query = $scope->queries->bind($select, $scope);
        $symbol = preg_replace('/\s+/', ' ', strtoupper(Tree::text($operator))) ?? 'IN';
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts((new TypeResolution(Dialect::Sqlite, $scope->diagnostics()))->boolean(), Nullability::MaybeNull);
        return QueryExpressionBinder::comparison($query, $facts, $source, [(new ExpressionBinder())->bind($value, $scope)], $symbol, true);
    }
}

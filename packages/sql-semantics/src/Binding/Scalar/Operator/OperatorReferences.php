<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Operator;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads PostgreSQL operator names written as OPERATOR(path.symbol) or as a bare symbol, and recognizes the built-in operators the binder already classifies.
 * @visibility SqlSemantics
 */
final class OperatorReferences
{
    /**
     * Built-in binary operators with their classified spelling; LIKE and ILIKE are the operators ~~ and ~~* themselves.
     */
    public const INFIX = ['+' => '+', '-' => '-', '*' => '*', '/' => '/', '%' => '%', '^' => '^', '<' => '<', '>' => '>', '=' => '=', '<=' => '<=', '>=' => '>=', '<>' => '<>', '||' => '||', '&' => '&', '|' => '|', '<<' => '<<', '>>' => '>>', '->' => '->', '->>' => '->>', '~~' => 'LIKE', '!~~' => 'NOT LIKE', '~~*' => 'ILIKE', '!~~*' => 'NOT ILIKE'];

    /**
     * Built-in prefix operators with their classified spelling.
     */
    public const PREFIX = ['+' => '+', '-' => '-', '~' => '~'];

    /**
     * Reads the qualifier and symbol; `!=` is PostgreSQL's spelling of `<>`, and a name with more than a database and a schema before the symbol is rejected.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function read(Node $operator, Scope $scope): QualifiedOperator
    {
        $name = Tree::child($operator, ['any_operator']);
        $parts = $name === null ? [Tree::text($operator)] : $scope->identifiers->parts($name);
        $symbol = (string) array_pop($parts);
        if (count($parts) > 2) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $operator);
        }
        return new QualifiedOperator($parts, $symbol === '!=' ? '<>' : $symbol);
    }

    /**
     * Returns the classified spelling when the reference names a built-in operator of this arity through the default search path or pg_catalog, and null otherwise.
     */
    public static function builtin(QualifiedOperator $operator, bool $prefix): ?string
    {
        if (!in_array($operator->qualifier, [[], ['pg_catalog']], true)) {
            return null;
        }
        return ($prefix ? self::PREFIX : self::INFIX)[$operator->symbol] ?? null;
    }
}

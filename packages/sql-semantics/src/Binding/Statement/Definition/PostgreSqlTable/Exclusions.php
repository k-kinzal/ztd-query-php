<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\PostgreSqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Relation\ConstraintActions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint;

/**
 * Reads the EXCLUDE table constraints of a PostgreSQL table declaration, which are not integrity constraints of the schema snapshot.
 * @visibility SqlSemantics
 */
final class Exclusions
{
    /**
     * Binds every EXCLUDE constraint in written order against the declaration scope.
     *
     * @return list<ExclusionConstraint>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $declaration, Scope $scope, QueryContext $context): array
    {
        $constraints = array_filter(Tree::outer($declaration, ['TableConstraint']), self::excludes(...));
        return array_values(array_map(static fn (Node $node): ExclusionConstraint => self::bind($node, $scope, $context), $constraints));
    }

    /**
     * Tells whether a table constraint is an EXCLUDE constraint.
     */
    public static function excludes(Node $constraint): bool
    {
        $body = Tree::child($constraint, ['ConstraintElem']) ?? $constraint;
        return strtoupper($body->tokens()[0]->text ?? '') === 'EXCLUDE';
    }

    /**
     * Binds one EXCLUDE constraint with its constraint attributes.
     *
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Node $constraint, Scope $scope, QueryContext $context): ExclusionConstraint
    {
        $attributes = array_map(static fn (Node $element): string => strtoupper(Tree::text($element)), Tree::outer($constraint, ['ConstraintAttributeElem']));
        return ConstraintActions::exclusion($constraint, $attributes, $scope, $context);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes operator, operator class, and operator family commands.
 * @visibility SqlSemantics
 */
final class Operators
{
    /**
     * Returns null for statements outside these forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\DropOperatorsStatement => new Tree('drop-operator', [Build::keyword('DROP OPERATOR' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::separated(array_map(self::signature(...), $statement->operators)), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\DropOperatorSetStatement => new Tree('drop-operator-set', [Build::keyword('DROP ' . $statement->object->kind->value . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier($statement->object->name->parts, $dialect), Build::keyword('USING'), Build::identifier([$statement->object->method], $dialect), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\CreateOperatorFamilyStatement => new Tree('create-operator-family', [Build::keyword('CREATE OPERATOR FAMILY'), Build::identifier($statement->name->parts, $dialect), Build::keyword('USING'), Build::identifier([$statement->method], $dialect)]),
            $statement instanceof Statement\CreateOperatorStatement => new Tree('create-operator', [Build::keyword('CREATE OPERATOR'), ObjectAddresses::operator($statement->name), DefinitionLists::write($statement->options)]),
            $statement instanceof Statement\AlterOperatorStatement => new Tree('alter-operator', [Build::keyword('ALTER OPERATOR'), self::signature($statement->operator), Build::keyword('SET'), DefinitionLists::write($statement->options)]),
            $statement instanceof Statement\CreateOperatorClassStatement => new Tree('create-operator-class', [Build::keyword('CREATE OPERATOR CLASS'), Build::identifier($statement->name->parts, $dialect), ...($statement->isDefault ? [Build::keyword('DEFAULT')] : []), Build::keyword('FOR TYPE'), TypeDeclaration::write($statement->type), Build::keyword('USING'), Build::identifier([$statement->method], $dialect), ...($statement->family === null ? [] : [Build::keyword('FAMILY'), Build::identifier($statement->family->parts, $dialect)]), Build::keyword('AS'), Build::separated(array_map(OperatorSetMembers::member(...), $statement->members))]),
            $statement instanceof Statement\AddOperatorFamilyMembersStatement => new Tree('alter-operator-family', [Build::keyword('ALTER OPERATOR FAMILY'), Build::identifier($statement->family->parts, $dialect), Build::keyword('USING'), Build::identifier([$statement->method], $dialect), Build::keyword('ADD'), Build::separated(array_map(OperatorSetMembers::member(...), $statement->members))]),
            $statement instanceof Statement\DropOperatorFamilyMembersStatement => new Tree('alter-operator-family', [Build::keyword('ALTER OPERATOR FAMILY'), Build::identifier($statement->family->parts, $dialect), Build::keyword('USING'), Build::identifier([$statement->method], $dialect), Build::keyword('DROP'), Build::separated(array_map(OperatorSetMembers::removal(...), $statement->members))]),
            default => null,
        };
    }

    /**
     * The operator symbol followed by its parenthesized operand types.
     * @throws InvalidStructure
     */
    public static function signature(OperatorIdentity $operator): Tree
    {
        return new Tree('operator-signature', [ObjectAddresses::operator($operator->name), Build::parentheses(Build::separated([ObjectAddresses::operand($operator->left), ObjectAddresses::operand($operator->right)]))]);
    }
}

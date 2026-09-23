<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Serializes routine identities and their typed argument signatures.
 * @visibility SqlSemantics
 */
final class Routines
{
    /**
     * Writes the applicable operands of each deletion form without consulting source SQL.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof MySql\DropFunctionStatement || $statement instanceof MySql\DropProcedureStatement) {
            return new Tree('drop-routine', [Build::keyword('DROP ' . ($statement instanceof MySql\DropFunctionStatement ? 'FUNCTION' : 'PROCEDURE') . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier($statement->name->parts, Dialect::MySql)]);
        }
        if (!$statement instanceof PostgreSql\DropFunctionsStatement && !$statement instanceof PostgreSql\DropProceduresStatement && !$statement instanceof PostgreSql\DropRoutinesStatement && !$statement instanceof PostgreSql\DropAggregatesStatement) {
            return null;
        }
        $operation = match (true) {
            $statement instanceof PostgreSql\DropFunctionsStatement => 'FUNCTION',
            $statement instanceof PostgreSql\DropProceduresStatement => 'PROCEDURE',
            $statement instanceof PostgreSql\DropRoutinesStatement => 'ROUTINE',
            $statement instanceof PostgreSql\DropAggregatesStatement => 'AGGREGATE',
        };
        $targets = $statement instanceof PostgreSql\DropAggregatesStatement ? array_map(self::aggregate(...), $statement->targets) : array_map(self::routine(...), $statement->targets);
        return new Tree('drop-routine', [Build::keyword('DROP ' . $operation . ($statement->ifExists ? ' IF EXISTS' : '')), Build::separated($targets), Build::keyword($statement->behavior->value)]);
    }

    /**
     * An omitted signature stays omitted; an empty signature retains its parentheses.
     */
    public static function routine(Routine\RoutineByName|Routine\RoutineBySignature $target): Tree
    {
        return new Tree('routine-identity', [Build::identifier($target->name->parts, Dialect::PostgreSql), ...($target instanceof Routine\RoutineBySignature ? [Build::parentheses(Build::separated(array_map(self::parameter(...), $target->parameters)))] : [])]);
    }

    /**
     * Retains the direct-input boundary and the star spelling for zero-argument aggregates.
     */
    public static function aggregate(Routine\ZeroArgumentAggregate|Routine\OrdinaryAggregate|Routine\OrderedSetAggregate $target): Tree
    {
        $signature = match (true) {
            $target instanceof Routine\ZeroArgumentAggregate => Build::keyword('*'),
            $target instanceof Routine\OrdinaryAggregate => Build::separated(array_map(self::parameter(...), $target->parameters)),
            $target instanceof Routine\OrderedSetAggregate => new Tree('ordered-set-signature', [Build::separated(array_map(self::parameter(...), $target->direct)), Build::keyword('ORDER BY'), Build::separated(array_map(self::parameter(...), $target->ordered))]),
        };
        return new Tree('aggregate-identity', [Build::identifier($target->name->parts, Dialect::PostgreSql), Build::parentheses($signature)]);
    }

    /**
     * Writes names as identifiers and types as declared structures, never raw SQL payloads.
     */
    public static function parameter(Routine\RoutineParameter|Routine\AggregateParameter $parameter): Tree
    {
        $type = $parameter->type instanceof Routine\ColumnTypeReference ? new Tree('column-type', [Build::identifier($parameter->type->name->parts, Dialect::PostgreSql), Build::keyword('%TYPE')]) : TypeDeclaration::write($parameter->type);
        return new Tree('routine-parameter', [Build::keyword($parameter->mode->value), ...($parameter->name === null ? [] : [Build::identifier([$parameter->name], Dialect::PostgreSql)]), ...($parameter->setOf ? [Build::keyword('SETOF')] : []), $type]);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Routine as Statement;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Statements;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes CREATE FUNCTION and CREATE PROCEDURE from their declarations, attributes, and bodies.
 * @visibility SqlSemantics
 */
final class RoutineDefinitions
{
    /**
     * Returns null for statements outside the routine definition forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateFunctionStatement => self::definition($statement, 'FUNCTION', [Build::keyword('RETURNS'), ...($statement->result->setOf ? [Build::keyword('SETOF')] : []), self::type($statement->result->type)], $statement->window),
            $statement instanceof Statement\CreateTableFunctionStatement => self::definition($statement, 'FUNCTION', [Build::keyword('RETURNS TABLE'), Build::parentheses(Build::separated(array_map(static fn (Declaration\ResultColumn $column): Tree => new Tree('result-column', [Build::identifier([$column->name], Dialect::PostgreSql), self::type($column->type)]), $statement->columns)))], $statement->window),
            $statement instanceof Statement\CreateOutputFunctionStatement => self::definition($statement, 'FUNCTION', [], $statement->window),
            $statement instanceof Statement\CreateProcedureStatement => self::definition($statement, 'PROCEDURE', [], false),
            default => null,
        };
    }

    /**
     * Writes the parts every routine definition shares around its result clause.
     * @param list<Tree> $result
     */
    public static function definition(Statement\CreateFunctionStatement|Statement\CreateTableFunctionStatement|Statement\CreateOutputFunctionStatement|Statement\CreateProcedureStatement $statement, string $class, array $result, bool $window): Tree
    {
        $implementation = $statement->implementation;
        return new Tree('create-routine', [
            Build::keyword('CREATE' . ($statement->orReplace ? ' OR REPLACE ' : ' ') . $class),
            new Tree('routine-signature', [Build::identifier($statement->name->parts, Dialect::PostgreSql), Build::parentheses(Build::separated(array_map(self::parameter(...), $statement->parameters)))]),
            ...$result,
            Build::keyword('LANGUAGE'),
            Build::identifier([$implementation->language], Dialect::PostgreSql),
            ...($implementation->transforms === [] ? [] : [Build::keyword('TRANSFORM'), Build::separated(array_map(static fn (TypeDescriptor $type): Tree => new Tree('transform', [Build::keyword('FOR TYPE'), TypeDeclaration::write($type)]), $implementation->transforms))]),
            ...($window ? [Build::keyword('WINDOW')] : []),
            ...array_map(RoutineAttributes::write(...), $statement->options),
            self::body($implementation->body),
        ]);
    }

    /**
     * Writes a parameter declaration with its default value.
     */
    public static function parameter(Declaration\ParameterDeclaration $declaration): Tree
    {
        return new Tree('parameter-declaration', [Routines::parameter($declaration->parameter), ...($declaration->default === null ? [] : [Build::keyword('DEFAULT'), Expressions::write($declaration->default)])]);
    }

    /**
     * Writes a declared type or a column-type reference.
     */
    public static function type(TypeDescriptor|ColumnTypeReference $type): Tree
    {
        return $type instanceof ColumnTypeReference ? new Tree('column-type', [Build::identifier($type->name->parts, Dialect::PostgreSql), Build::keyword('%TYPE')]) : TypeDeclaration::write($type);
    }

    /**
     * Writes a definition string, an object file with its symbol, or an inline SQL body.
     */
    public static function body(Declaration\RoutineBody $body): Tree
    {
        return match (true) {
            $body instanceof Declaration\DefinitionBody => new Tree('routine-body', [Build::keyword('AS'), Expressions::write($body->definition)]),
            $body instanceof Declaration\LinkedBody => new Tree('routine-body', [Build::keyword('AS'), Build::separated([Expressions::write($body->file), Expressions::write($body->symbol)])]),
            $body instanceof Declaration\ReturnBody => self::returned($body),
            $body instanceof Declaration\AtomicBody => new Tree('routine-body', [Build::keyword('BEGIN ATOMIC'), ...array_map(static fn (BoundStatement|Declaration\ReturnBody $step): Tree => new Tree('body-step', [$step instanceof Declaration\ReturnBody ? self::returned($step) : Statements::write($step), Build::keyword(';')]), $body->statements), Build::keyword('END')]),
            default => new Tree('routine-body', []),
        };
    }

    /**
     * RETURN followed by the returned expression.
     */
    public static function returned(Declaration\ReturnBody $body): Tree
    {
        return new Tree('return', [Build::keyword('RETURN'), Expressions::write($body->value)]);
    }
}

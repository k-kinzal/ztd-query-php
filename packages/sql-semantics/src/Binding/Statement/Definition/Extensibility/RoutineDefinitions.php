<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Routine\Parameters;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Routine as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE FUNCTION and CREATE PROCEDURE by their result form.
 * @visibility SqlSemantics
 */
final class RoutineDefinitions
{
    /**
     * Separates procedures, RETURNS TABLE, RETURNS type, and functions whose OUT parameters describe the result.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $name = self::name(Targets::name(Tree::child($source, ['func_name']) ?? throw new UnclassifiedSql('A routine requires its name.'), $context), $source);
        $procedure = in_array('PROCEDURE', array_map(static fn ($child): string => $child instanceof Node ? '' : strtoupper($child->text), $source->children), true);
        $parameters = self::parameters($source, $procedure, $context);
        $options = RoutineOptions::read($origin, $source, $context);
        $window = array_key_exists('WINDOW', RoutineBodies::items($source));
        $implementation = RoutineBodies::implementation($origin, $source, $name, $parameters, $context);
        $orReplace = Tree::child($source, ['opt_or_replace']) !== null;
        $columns = Tree::child($source, ['table_func_column_list']);
        $result = Tree::child($source, ['func_return']);
        if (($procedure && $window) || (!$procedure && $columns === null && $result === null && array_filter($parameters, static fn (Declaration\ParameterDeclaration $parameter): bool => $parameter->output()) === [])) {
            throw new InvalidSql($procedure ? InputViolation::RoutineAttribute : InputViolation::RoutineDefinition, $source);
        }
        try {
            return match (true) {
                $procedure => new Statement\CreateProcedureStatement($origin, $orReplace, $name, $parameters, $implementation, $options),
                $columns !== null => new Statement\CreateTableFunctionStatement($origin, $orReplace, $name, $parameters, self::columns($columns, $parameters, $context), $implementation, $window, $options),
                $result !== null => new Statement\CreateFunctionStatement($origin, $orReplace, $name, $parameters, self::result($result, $context), $implementation, $window, $options),
                default => new Statement\CreateOutputFunctionStatement($origin, $orReplace, $name, $parameters, $implementation, $window, $options),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineAttribute, $source, $error);
        }
    }

    /**
     * Reads each parameter with its default and checks the list before any body sees it.
     * @return list<Declaration\ParameterDeclaration>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function parameters(Node $source, bool $procedure, QueryContext $context): array
    {
        $parameters = [];
        foreach (Tree::outer(Tree::child($source, ['func_args_with_defaults']) ?? $source, ['func_arg_with_default']) as $item) {
            $argument = Tree::child($item, ['func_arg']) ?? throw new UnclassifiedSql('A parameter requires its declaration.');
            $default = Tree::child($item, ['a_expr']);
            if ($default !== null && Tree::outer($default, ['select_with_parens', 'columnref']) !== []) {
                throw new InvalidSql(InputViolation::RoutineParameter, $default);
            }
            try {
                $parameters[] = new Declaration\ParameterDeclaration(Parameters::routine($argument, $context), $default === null ? null : (new ExpressionBinder())->bind($default, new Scope($context->tables->identifiers, queries: $context)));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::RoutineParameter, $item, $error);
            }
        }
        try {
            Declaration\ParameterInvariant::parameters($parameters, $procedure);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineParameter, $source, $error);
        }
        return $parameters;
    }

    /**
     * RETURNS [SETOF] type.
     * @throws InvalidStructure
     */
    public static function result(Node $source, QueryContext $context): Declaration\RoutineResult
    {
        $type = Tree::child($source, ['func_type']) ?? $source;
        return new Declaration\RoutineResult(Parameters::type($type, $context), self::setOf($type));
    }

    /**
     * RETURNS TABLE (name type, ...) after input parameters only; a result column is never a set and is named once.
     * @param list<Declaration\ParameterDeclaration> $parameters
     * @return non-empty-list<Declaration\ResultColumn>
     * @throws InvalidSql
     */
    public static function columns(Node $source, array $parameters, QueryContext $context): array
    {
        $columns = [];
        foreach (Tree::outer($source, ['table_func_column']) as $column) {
            $type = Tree::child($column, ['func_type']) ?? $column;
            $name = Tree::child($column, ['param_name']) ?? $column;
            if (self::setOf($type)) {
                throw new InvalidSql(InputViolation::RoutineParameter, $column);
            }
            try {
                $columns[] = new Declaration\ResultColumn($context->tables->identifiers->name($name->tokens()[0]), Parameters::type($type, $context));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::RoutineParameter, $column, $error);
            }
        }
        try {
            Declaration\ParameterInvariant::table($parameters, $columns);
            return Collections::nonEmpty($columns);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineParameter, $source, $error);
        }
    }

    /**
     * Whether a declared type starts with SETOF.
     */
    public static function setOf(Node $type): bool
    {
        $declaration = Tree::child($type, ['Typename']) ?? $type;
        return strtoupper($declaration->tokens()[0]->text ?? '') === 'SETOF';
    }

    /**
     * Requires a routine name of at most three components.
     * @throws InvalidSql
     */
    public static function name(QualifiedName $name, Node $source): QualifiedName
    {
        try {
            CatalogInvariant::name($name, 3);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $source, $error);
        }
        return $name;
    }
}

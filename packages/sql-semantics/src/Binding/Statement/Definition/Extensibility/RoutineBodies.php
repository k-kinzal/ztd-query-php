<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds the language and body of a new routine; inline SQL bodies see the input parameters.
 * @visibility SqlSemantics
 */
final class RoutineBodies
{
    /**
     * Requires exactly one body: AS with a LANGUAGE, or an inline SQL body whose language is SQL.
     * @param list<Declaration\ParameterDeclaration> $parameters
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function implementation(Origin $origin, Node $source, QualifiedName $name, array $parameters, QueryContext $context): Declaration\RoutineImplementation
    {
        $items = self::items($source);
        $inline = Tree::child($source, ['opt_routine_body']);
        $definition = $items['AS'] ?? null;
        $language = $items['LANGUAGE'] ?? null;
        if (($definition === null) === ($inline === null) || ($language === null && $inline === null)) {
            throw new InvalidSql(InputViolation::RoutineDefinition, $source);
        }
        $body = $inline === null ? self::definition($definition ?? $source) : self::inline($origin, $inline, $name, $parameters, $context);
        $transforms = $items['TRANSFORM'] ?? null;
        try {
            return new Declaration\RoutineImplementation(
                $language === null ? 'sql' : ObjectAddresses::provider($language->tokens()[1] ?? throw new UnclassifiedSql('LANGUAGE requires a name.'), $context),
                $transforms === null ? [] : array_map(static fn (Node $type): TypeDescriptor => (new TypeReader(Dialect::PostgreSql))->read($type), Tree::outer($transforms, ['Typename'])),
                $body,
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineDefinition, $source, $error);
        }
    }

    /**
     * Returns the LANGUAGE, AS, TRANSFORM, and WINDOW items by keyword; each is given at most once.
     * @return array<string, Node>
     * @throws InvalidSql
     */
    public static function items(Node $source): array
    {
        $items = [];
        foreach (Tree::outer($source, ['createfunc_opt_item']) as $item) {
            if (Tree::child($item, ['common_func_opt_item']) !== null) {
                continue;
            }
            $keyword = strtoupper($item->tokens()[0]->text);
            if (array_key_exists($keyword, $items)) {
                throw new InvalidSql(InputViolation::RoutineAttribute, $item);
            }
            $items[$keyword] = $item;
        }
        return $items;
    }

    /**
     * AS 'definition' or AS 'object file', 'link symbol'.
     * @throws InvalidSql
     */
    public static function definition(Node $item): Declaration\DefinitionBody|Declaration\LinkedBody
    {
        $strings = [];
        foreach (Tree::outer($item, ['Sconst']) as $string) {
            $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($string->tokens()[0]);
            $strings[] = $literal instanceof Literal ? $literal : throw new InvalidSql(InputViolation::RoutineDefinition, $string);
        }
        try {
            return count($strings) === 2 ? new Declaration\LinkedBody($strings[0], $strings[1]) : new Declaration\DefinitionBody($strings[0] ?? throw new InvalidSql(InputViolation::RoutineDefinition, $item));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineDefinition, $item, $error);
        }
    }

    /**
     * RETURN expression, or BEGIN ATOMIC with statements and RETURN steps, bound with the input parameters in an enclosing scope; empty statements are dropped.
     * @param list<Declaration\ParameterDeclaration> $parameters
     * @throws UnclassifiedSql
     */
    public static function inline(Origin $origin, Node $body, QualifiedName $name, array $parameters, QueryContext $context): Declaration\ReturnBody|Declaration\AtomicBody
    {
        $return = Tree::child($body, ['ReturnStmt']);
        $inner = new QueryContext($context->tables, $context->ids, parameterTypes: self::types($parameters));
        $scope = new Scope($context->tables->identifiers, [self::relation($origin, $body, $name, $parameters, $inner)], queries: $inner);
        if ($return !== null) {
            return self::returned($return, $scope);
        }
        $steps = [];
        foreach (Tree::outer($body, ['routine_body_stmt']) as $step) {
            $returned = Tree::child($step, ['ReturnStmt']);
            $statement = Tree::child($step, ['stmt']);
            if ($returned !== null) {
                $steps[] = self::returned($returned, $scope);
            } elseif ($statement !== null) {
                $steps[] = self::statement($statement, $scope, $inner);
            }
        }
        return new Declaration\AtomicBody($steps);
    }

    /**
     * Binds a RETURN expression.
     * @throws UnclassifiedSql
     */
    public static function returned(Node $source, Scope $scope): Declaration\ReturnBody
    {
        return new Declaration\ReturnBody((new ExpressionBinder())->bind(Tree::child($source, ['a_expr']) ?? throw new UnclassifiedSql('RETURN requires a value.'), $scope));
    }

    /**
     * Binds one body statement with the parameters visible after the statement's own relations.
     * @throws UnclassifiedSql
     */
    public static function statement(Node $source, Scope $scope, QueryContext $context): BoundStatement
    {
        $command = Tree::significant($source)[0] ?? null;
        if (!$command instanceof Node) {
            throw new UnclassifiedSql('A body step requires a statement.');
        }
        return (new StatementBinder($context->tables))->node($command, $command, $context, $scope);
    }

    /**
     * The positional types of the input parameters, up to the first type the schema does not resolve.
     * @param list<Declaration\ParameterDeclaration> $parameters
     * @return list<TypeDescriptor>
     */
    public static function types(array $parameters): array
    {
        $types = [];
        foreach ($parameters as $declaration) {
            if (!$declaration->input()) {
                continue;
            }
            $type = self::type($declaration->parameter->type);
            if ($type === null) {
                break;
            }
            $types[] = $type;
        }
        return $types;
    }

    /**
     * A declared type, or the type of the column a %TYPE reference resolves to.
     */
    public static function type(TypeDescriptor|ColumnTypeReference $type): ?TypeDescriptor
    {
        return $type instanceof ColumnTypeReference ? $type->binding?->column->type : $type;
    }

    /**
     * The named input parameters as a relation named after the routine, so that name.parameter also resolves.
     * @param list<Declaration\ParameterDeclaration> $parameters
     */
    public static function relation(Origin $origin, Node $source, QualifiedName $name, array $parameters, QueryContext $context): TableReference
    {
        $columns = [];
        foreach ($parameters as $declaration) {
            $type = self::type($declaration->parameter->type);
            if ($declaration->input() && $declaration->parameter->name !== null && $type !== null) {
                $columns[] = new ColumnDefinition($declaration->parameter->name, $type, Nullability::MaybeNull, $source);
            }
        }
        $routine = $name->parts[count($name->parts) - 1];
        return new TableReference($context->ids->relation(), $origin->scopeId, new TableDefinition('', $routine, $columns, [], $source), new QualifiedName([$routine]), null, $source);
    }
}

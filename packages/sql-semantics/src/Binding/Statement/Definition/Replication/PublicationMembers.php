<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads a publication object list, where an unprefixed name continues the kind of the object before it.
 * @visibility SqlSemantics
 */
final class PublicationMembers
{
    /**
     * Resolves each continuation as PostgreSQL's preprocessing does and rejects a list that starts with one.
     * @return list<Operand\PublicationMember>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Node $list, Origin $origin, QueryContext $context): array
    {
        $objects = [];
        $kind = null;
        foreach (Tree::outer($list, ['PublicationObjSpec']) as $spec) {
            $first = strtoupper($spec->tokens()[0]->text);
            $kind = match ($first) {
                'TABLE' => 'table',
                'TABLES' => 'schema',
                default => $kind ?? throw new InvalidSql(InputViolation::PublicationObject, $spec),
            };
            try {
                $objects[] = $kind === 'table' ? self::table($spec, $origin, $context) : self::schema($spec, $context);
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::PublicationObject, $spec, $error);
            }
        }
        return $objects;
    }

    /**
     * A table with its optional column list and row filter; CURRENT_SCHEMA is not a table name.
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function table(Node $spec, Origin $origin, QueryContext $context): Operand\PublishedTable
    {
        $identifiers = $context->tables->identifiers;
        $relation = Tree::outer($spec, ['qualified_name'])[0] ?? null;
        $head = Tree::child($spec, ['ColId']);
        if ($relation === null && $head === null) {
            throw new InvalidSql(InputViolation::PublicationObject, $spec);
        }
        $indirection = Tree::child($spec, ['indirection']);
        $parts = $relation !== null ? $identifiers->parts($relation) : [...$identifiers->parts($head), ...($indirection === null ? [] : $identifiers->parts($indirection))];
        $declaration = $context->tables->resolve($parts, $relation ?? $spec);
        $name = $context->tables->name($parts, $declaration);
        $only = in_array('ONLY', array_map(static fn ($token): string => strtoupper($token->text), array_slice($spec->tokens(), 0, 2)), true);
        $table = $only
            ? new OnlyTableReference($context->ids->relation(), $origin->scopeId, $declaration, $name, null, $spec)
            : new TableReference($context->ids->relation(), $origin->scopeId, $declaration, $name, null, $spec);
        $columnList = Tree::child($spec, ['opt_column_list']);
        $columns = $columnList === null ? [] : array_map(static fn (Node $column): string => $identifiers->name($column->tokens()[0]), Tree::outer($columnList, ['columnElem']));
        $where = Tree::child($spec, ['OptWhereClause']);
        $filter = $where === null ? null : (new ExpressionBinder())->bind(Tree::child($where, ['a_expr']) ?? throw new UnclassifiedSql('WHERE requires its filter.'), new Scope($identifiers, [$table], queries: $context));
        return new Operand\PublishedTable($table, $columns, $filter);
    }

    /**
     * A named schema or CURRENT_SCHEMA; a qualified name, column list, or filter is rejected.
     * @throws InvalidSql
     */
    public static function schema(Node $spec, QueryContext $context): Operand\PublishedSchema|Operand\PublishedCurrentSchema
    {
        if (Tree::child($spec, ['opt_column_list', 'OptWhereClause', 'indirection', 'extended_relation_expr']) !== null) {
            throw new InvalidSql(InputViolation::PublicationObject, $spec);
        }
        $name = Tree::child($spec, ['ColId']);
        if ($name === null) {
            return new Operand\PublishedCurrentSchema();
        }
        return new Operand\PublishedSchema($context->tables->identifiers->name($name->tokens()[0]));
    }
}

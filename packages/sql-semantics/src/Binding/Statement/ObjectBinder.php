<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds named schema operations and query-backed declarations as distinct forms.
 *
 * @visibility SqlSemantics
 */
final class ObjectBinder
{
    /**
     * Dispatches by the outer schema operation.
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $virtual = Tree::outer($source, ['create_vtab'])[0] ?? null;
        if ($virtual !== null) {
            return VirtualTableBinder::bind($origin, $source, $virtual, $context);
        }
        $trigger = Tree::child($source, ['trigger_decl']);
        if ($trigger !== null) {
            return Trigger\SqliteTriggerBinder::bind($origin, $source, $trigger, $context);
        }
        $owned = DropBinder::bind($origin, $source, $context);
        if ($owned !== null) {
            return $owned;
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        if (($words[0] ?? '') === 'DROP') {
            return self::drop($origin, $source, $context);
        }
        if (($words[0] ?? '') === 'ALTER' && ($words[1] ?? '') === 'TABLE') {
            return (new AlterTableBinder())->bind($origin, $source, $context);
        }
        if (($words[0] ?? '') !== 'CREATE') {
            return null;
        }
        $query = array_values(array_filter(Tree::outer($source, ['SelectStmt', 'select_stmt', 'select', 'query_expression', 'columnDef', 'column_def', 'columnlist', 'TableConstraint', 'table_constraint_def']), static fn (Node $node): bool => in_array($node->name, ['SelectStmt', 'select_stmt', 'select', 'query_expression'], true)))[0] ?? null;
        if ($query === null) {
            return null;
        }
        $as = array_search('AS', $words, true);
        $prefix = array_slice($words, 0, $as === false ? count($words) : $as);
        if (in_array('VIEW', $prefix, true)) {
            return self::view($origin, $source, $query, $context);
        }
        if (in_array('TABLE', $prefix, true)) {
            return self::tableAs($origin, $source, $query, $context);
        }
        return null;
    }

    /**
     * Binds table, view, index and trigger deletion targets.
     */
    public static function drop(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        $nodes = Tree::outer($source, ['any_name', 'table_ident', 'fullname', 'sp_name']);
        $names = array_map(static fn (Node $name): QualifiedName => new QualifiedName($context->tables->identifiers->parts($name)), $nodes);
        if ($names === []) {
            return null;
        }
        $exists = in_array('IF', $words, true) && in_array('EXISTS', $words, true);
        $behavior = DropBehavior::tryFrom($words[count($words) - 1]) ?? DropBehavior::Default;
        $temporary = ($words[1] ?? '') === 'TEMPORARY';
        return match ($words[$temporary ? 2 : 1] ?? '') {
            'TABLE', 'TABLES' => new Statement\DropTableStatement($origin, $names, $exists, $behavior, $temporary ? \SqlSemantics\Model\Definition\TableDropScope::Temporary : \SqlSemantics\Model\Definition\TableDropScope::Visible),
            'VIEW' => new Statement\DropViewStatement($origin, $names, $exists, $behavior),
            'INDEX' => new Statement\DropIndexStatement($origin, $names, $exists, $behavior),
            'TRIGGER' => new Statement\DropTriggerStatement($origin, $names[0], $exists),
            default => null,
        };
    }

    /**
     * Binds a view's named outputs and mandatory query.
     */
    public static function view(Origin $origin, Node $source, Node $query, QueryContext $context): Statement\CreateViewStatement
    {
        $name = self::name($source, $context);
        $aliases = Tree::outer($source, ['opt_column_list', 'opt_name_list', 'view_list_opt', 'eidlist_opt'])[0] ?? null;
        $columns = $aliases === null ? [] : array_values(array_filter($context->tables->identifiers->parts($aliases), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
        $text = strtoupper(Tree::text($source));
        $check = !str_contains($text, 'CHECK OPTION') ? \SqlSemantics\Model\Definition\ViewCheck::None : (str_contains($text, 'LOCAL CHECK OPTION') ? \SqlSemantics\Model\Definition\ViewCheck::Local : \SqlSemantics\Model\Definition\ViewCheck::Cascaded);
        return new Statement\CreateViewStatement($origin, $name, $context->bind($query), $columns, preg_match('/^CREATE (TEMP|TEMPORARY) /', $text) === 1, str_contains($text, 'OR REPLACE'), str_contains($text, 'IF NOT EXISTS'), $check);
    }

    /**
     * Binds query-derived table columns without fabricating an empty CREATE TABLE body.
     * @throws UnclassifiedSql
     */
    public static function tableAs(Origin $origin, Node $source, Node $query, QueryContext $context): Statement\CreateTableAsStatement
    {
        $parsed = (new \SqlSemantics\Ast\SchemaReader($context->tables->identifiers, $context->tables->defaultSchema, $context->tables->diagnostics->report(...)))->table($source);
        if ($parsed->columns !== []) {
            throw new UnclassifiedSql('A table declaration with both explicit columns and an input query requires its own form.');
        }
        $scope = new \SqlSemantics\Binding\Scope($context->tables->identifiers, queries: $context);
        $aliases = Tree::outer($source, ['opt_column_list', 'opt_name_list'])[0] ?? null;
        $columns = $aliases === null ? [] : array_values(array_filter($context->tables->identifiers->parts($aliases), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
        return new Statement\CreateTableAsStatement($origin, self::name($source, $context), $context->bind($query), $columns, \SqlSemantics\Binding\Schema\TablePropertiesBinder::bind($parsed, $scope), !str_contains(strtoupper(Tree::text($source)), 'WITH NO DATA'), ($parsed->options['if_not_exists'] ?? false) === true);
    }

    /**
     * Reads the declaration's name without entering its query.
     * @throws UnclassifiedSql
     */
    public static function name(Node $source, QueryContext $context): QualifiedName
    {
        $header = Tree::outer($source, ['create_table', 'create_vtab'])[0] ?? $source;
        $node = Tree::outer($header, ['qualified_name', 'table_ident', 'nm'])[0] ?? null;
        if ($node === null) {
            throw new UnclassifiedSql('A declaration requires an object name.');
        }
        $parts = $context->tables->identifiers->parts($node);
        $suffix = Tree::child($header, ['dbnm']);
        if ($suffix !== null) {
            array_push($parts, ...$context->tables->identifiers->parts($suffix));
        }
        return new QualifiedName($parts);
    }
}

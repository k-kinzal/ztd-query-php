<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\View;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlRemovals;
use SqlSemantics\Binding\Statement\ObjectBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds view declarations, keeping each dialect's declaration options as typed properties.
 * @visibility SqlSemantics
 */
final class ViewBinder
{
    /**
     * Returns null when the declaration is not an ordinary view.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?CreateViewStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        if (($words[0] ?? '') !== 'CREATE' || in_array('MATERIALIZED', $words, true)) {
            return null;
        }
        $query = \SqlSemantics\Binding\Query\QueryNodes::legacyContainer($source) ?? Tree::outer($source, ['SelectStmt', 'select_stmt', 'select', 'query_expression', 'view_select', 'view_query_block'])[0] ?? null;
        if ($query === null || !in_array('VIEW', array_slice($words, 0, is_int($position = array_search('AS', $words, true)) ? $position : count($words)), true)) {
            return null;
        }
        if ($query->name === 'view_query_block') {
            $query = Tree::outer($query, ['query_expression'])[0] ?? $query;
        }
        $text = strtoupper(Tree::text($source));
        $check = !str_contains($text, 'CHECK OPTION') ? ViewCheck::None : (str_contains($text, 'LOCAL CHECK OPTION') ? ViewCheck::Local : ViewCheck::Cascaded);
        $properties = match ($origin->dialect) {
            Dialect::MySql => self::mysql($source, $context),
            Dialect::PostgreSql => new PostgreSqlViewProperties(in_array('RECURSIVE', array_slice($words, 0, 5), true), \SqlSemantics\Binding\Schema\StorageParameters::read($source, new Scope($context->tables->identifiers, queries: $context), ['SelectStmt'])),
            Dialect::Sqlite => null,
        };
        return new CreateViewStatement($origin, ObjectBinder::name($source, $context), $context->bind($query), self::columns($source, $context), preg_match('/^CREATE (OR REPLACE )?(TEMP|TEMPORARY) /', $text) === 1, str_contains($text, 'OR REPLACE'), $origin->dialect === Dialect::Sqlite && Tree::child($source, ['ifnotexists']) !== null, $check, $properties);
    }

    /**
     * @return list<string>
     */
    public static function columns(Node $source, QueryContext $context): array
    {
        $aliases = Tree::outer($source, ['opt_column_list', 'columnList', 'opt_derived_column_list', 'view_list_opt', 'eidlist_opt', 'SelectStmt', 'select_stmt', 'select', 'query_expression', 'view_select', 'view_query_block'])[0] ?? null;
        if ($aliases === null || in_array($aliases->name, ['SelectStmt', 'select_stmt', 'select', 'query_expression', 'view_select', 'view_query_block'], true)) {
            return [];
        }
        return array_values(array_filter($context->tables->identifiers->parts($aliases), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
    }

    /**
     * Reads the algorithm, definer, and privilege context of a MySQL view.
     */
    public static function mysql(Node $source, QueryContext $context): MySqlViewProperties
    {
        $algorithm = Tree::outer($source, ['view_algorithm'])[0] ?? null;
        $definer = Tree::outer($source, ['definer'])[0] ?? null;
        $security = Tree::outer($source, ['view_suid'])[0] ?? null;
        $securityTokens = $security?->tokens() ?? [];
        $algorithmTokens = $algorithm?->tokens() ?? [];
        return new MySqlViewProperties(
            $algorithmTokens === [] ? ViewAlgorithm::Undefined : ViewAlgorithm::from(strtoupper($algorithmTokens[count($algorithmTokens) - 1]->text)),
            $definer === null ? null : MySqlRemovals::accounts($definer, $context->tables->identifiers)[0],
            $securityTokens === [] ? ViewSecurity::Definer : ViewSecurity::from(strtoupper($securityTokens[count($securityTokens) - 1]->text)),
        );
    }
}

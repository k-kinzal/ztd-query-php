<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Statement\Definition\View\ViewBinder;
use SqlSemantics\Binding\Statement\ObjectBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds MySQL ALTER VIEW with the same declaration properties as CREATE VIEW.
 * @visibility SqlSemantics
 */
final class ViewAlterations
{
    /**
     * Returns null when the ALTER statement does not alter a view.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?AlterViewStatement
    {
        $tail = Tree::child($source, ['view_tail']);
        if ($tail === null) {
            return null;
        }
        $query = QueryNodes::legacyContainer($tail) ?? Tree::outer($tail, ['select_stmt', 'select', 'query_expression', 'view_select', 'view_query_block'])[0] ?? throw new UnclassifiedSql('ALTER VIEW requires its query.');
        if ($query->name === 'view_query_block') {
            $query = Tree::outer($query, ['query_expression'])[0] ?? $query;
        }
        return new AlterViewStatement($origin, ObjectBinder::name($tail, $context), $context->bind($query), ViewBinder::columns($tail, $context), self::check($tail), ViewBinder::mysql($source, $context));
    }

    /**
     * Reads WITH [CASCADED | LOCAL] CHECK OPTION; a bare CHECK OPTION is cascaded.
     */
    public static function check(Node $tail): ViewCheck
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), (Tree::outer($tail, ['view_check_option'])[0] ?? null)?->tokens() ?? []);
        return match (true) {
            $words === [] => ViewCheck::None,
            in_array('LOCAL', $words, true) => ViewCheck::Local,
            default => ViewCheck::Cascaded,
        };
    }
}

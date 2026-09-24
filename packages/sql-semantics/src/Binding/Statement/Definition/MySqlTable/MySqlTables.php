<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes MySQL table renaming and alteration requests and the partitioning and generated column parser entries.
 * @visibility SqlSemantics
 */
final class MySqlTables
{
    /**
     * Returns null for statements outside MySQL table definition.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        if ($statement->name === 'rename' && Tree::child($statement, ['table_to_table_list']) !== null) {
            return TableRenamings::bind($origin, $statement, $context);
        }
        if (in_array($statement->name, ['partition_entry', 'parse_gcol_expr'], true)) {
            return EntryPoints::bind($origin, $statement, $context);
        }
        $words = array_map(static fn (Token $token): string => $token->name, array_slice($statement->tokens(), 0, 3));
        if (($words[0] ?? '') === 'ALTER' && in_array('TABLE_SYM', array_slice($words, 1), true) && Tree::child($statement, ['table_ident']) !== null) {
            return AlterTables::bind($origin, $statement, $context);
        }
        return null;
    }
}

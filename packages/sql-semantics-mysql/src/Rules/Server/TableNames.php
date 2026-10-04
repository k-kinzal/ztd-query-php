<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Resolves the tables a server administration statement names and reports a table named twice.
 *
 * Rule: MYSQL-SERVER-TABLES-001. Each name resolves by
 * MYSQL-CHANGE-TARGET-001 (the current database or the database written;
 * common table expressions are not visible) and its resolution is the
 * relation fact of the node that holds it. The server adds every table of
 * the list to the statement's table list (Query_block::add_table_to_list
 * without TL_OPTION_ALIAS), which rejects a second entry whose alias (the
 * table name when no alias is written) and database equal an earlier one's
 * with ER_NONUNIQ_TABLE; that is the diagnostic NonUniqueTable, compared
 * under the context's relation name comparison. Precision: a declared table
 * contributes the known types of its columns. Terminates: one pass over the
 * list and over the names before each entry.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableNames
{
    /**
     * Records the resolution of each table at its node and reports a repeated alias or name.
     *
     * @param list<array{Node, QualifiedName, Name|null}> $tables Each node, the table name it holds and its alias
     * @return list<RelationFact> The facts of the tables in order
     */
    public function record(Derivation $derivation, array $tables): array
    {
        $targets = new Targets();
        $context = $derivation->context;
        $seen = [];
        $facts = [];
        foreach ($tables as [$node, $name, $alias]) {
            $facts[] = $derivation->target($node, $targets->target($derivation, $name));
            $key = [($name->schema ?? $context->searchPath[0])->value, ($alias ?? $name->name)->value];
            foreach ($seen as [$schema, $earlier]) {
                if ($context->relationNames->equal($schema, $key[0]) && $context->relationNames->equal($earlier, $key[1])) {
                    $derivation->report(new NonUniqueTable($alias ?? $name->name));
                    break;
                }
            }
            $seen[] = $key;
        }

        return $facts;
    }
}

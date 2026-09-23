<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\MySql as Options;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds storage-engine maintenance requests against the supplied table definitions.
 * @visibility SqlSemantics
 */
final class MySqlTables
{
    /**
     * Keeps each operation's applicable flags in its own native domain.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        $verb = strtoupper($node->tokens()[0]->text ?? '');
        if ($origin->dialect !== Dialect::MySql || !in_array($verb, ['CHECK', 'CHECKSUM', 'REPAIR', 'ANALYZE', 'OPTIMIZE'], true)) {
            return null;
        }
        $tables = self::tables($origin, $node, $context);
        $local = Tree::outer($node, ['opt_no_write_to_binlog'])[0] ?? null;
        $binlog = $local === null || !Tree::hasTokens($local) ? Options\BinlogPolicy::Write : Options\BinlogPolicy::Omit;
        $histogram = Tree::outer($node, ['opt_histogram'])[0] ?? null;
        if ($histogram !== null && Tree::hasTokens($histogram)) {
            return Histograms::bind($origin, $histogram, $context, $tables, $binlog);
        }
        return match ($verb) {
            'CHECK' => new Statement\CheckTablesStatement($origin, $tables, array_map(static fn (Node $option): Options\CheckOption => Options\CheckOption::from(strtoupper(Tree::text($option))), Tree::outer($node, ['mi_check_type']))),
            'REPAIR' => new Statement\RepairTablesStatement($origin, $tables, array_map(static fn (Node $option): Options\RepairOption => Options\RepairOption::from(strtoupper(Tree::text($option))), Tree::outer($node, ['mi_repair_type'])), $binlog),
            'ANALYZE' => new Statement\AnalyzeTablesStatement($origin, $tables, $binlog),
            'OPTIMIZE' => new Statement\OptimizeTablesStatement($origin, $tables, $binlog),
            'CHECKSUM' => new Statement\ChecksumTablesStatement($origin, $tables, Options\ChecksumMode::from(strtoupper(Tree::text(Tree::outer($node, ['opt_checksum_type'])[0] ?? new Node('default_checksum', 0, []))))),
        };
    }

    /**
     * @return non-empty-list<TableReference> Resolved or diagnosed physical table occurrences
     * @throws UnclassifiedSql
     */
    public static function tables(Origin $origin, Node $node, QueryContext $context): array
    {
        $tables = [];
        foreach (Tree::outer($node, ['table_ident']) as $name) {
            $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
            if (!$table instanceof TableReference) {
                throw new UnclassifiedSql('MySQL maintenance requires physical table occurrences.');
            }
            $tables[] = $table;
        }
        if ($tables === []) {
            throw new UnclassifiedSql('Table maintenance requires at least one target.');
        }
        return $tables;
    }
}

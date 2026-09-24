<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\FlushTarget;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration as Statement;

/**
 * Binds FLUSH TABLES forms and FLUSH option lists.
 * @visibility SqlSemantics
 */
final class Flushes
{
    /**
     * Distinguishes the table forms by their lock clause and reads option lists as typed targets.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $local = Tree::child($node, ['opt_no_write_to_binlog']);
        $binlog = $local === null || !Tree::hasTokens($local) ? BinlogPolicy::Write : BinlogPolicy::Omit;
        $options = Tree::outer($node, ['flush_option']);
        if ($options !== []) {
            return new Statement\FlushServerStatement($origin, array_map(static fn (Node $option): FlushTarget => self::target($option, $context), $options), $binlog);
        }
        $tables = self::tables($origin, $node, $context);
        $lock = Tree::outer($node, ['opt_flush_lock'])[0] ?? null;
        $clause = $lock === null ? '' : strtoupper($lock->tokens()[0]->text ?? '');
        return match ($clause) {
            'WITH' => new Statement\FlushTablesWithReadLockStatement($origin, $tables, $binlog),
            'FOR' => new Statement\FlushTablesForExportStatement($origin, $tables, $binlog),
            default => new Statement\FlushTablesStatement($origin, $tables, $binlog),
        };
    }

    /**
     * Reads one flush option; USER_RESOURCES is the spelling of the RESOURCES token.
     * @throws UnclassifiedSql
     */
    public static function target(Node $option, QueryContext $context): FlushTarget
    {
        $words = implode(' ', array_map(static fn ($token): string => strtoupper($token->text), $option->tokens()));
        if (str_starts_with($words, 'RELAY LOGS')) {
            return new RelayLogFlush(Channels::read($option, $context->tables->identifiers));
        }
        return ServerFlush::tryFrom($words) ?? throw new UnclassifiedSql('Unclassified FLUSH option: ' . $words);
    }

    /**
     * @return list<TableReference>
     * @throws UnclassifiedSql
     */
    public static function tables(Origin $origin, Node $node, QueryContext $context): array
    {
        $tables = [];
        foreach (Tree::outer($node, ['table_ident']) as $name) {
            $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
            if (!$table instanceof TableReference) {
                throw new UnclassifiedSql('FLUSH TABLES requires physical table occurrences.');
            }
            $tables[] = $table;
        }
        return $tables;
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\KeyCache;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CACHE INDEX t [INDEX (…)], … IN {cache | DEFAULT}`: a request to assign the indexes of MyISAM tables to a key cache.
 *
 * Mirrors PT_cache_index_stmt and PT_cache_index_partitions_stmt. Rule:
 * MYSQL-CACHE-INDEX-001. Each table resolves by MYSQL-SERVER-TABLES-001 and
 * its resolution is the relation fact of its CachedTable; a table named
 * twice is NonUniqueTable. A table with a partition selection is the only
 * table of the statement. The key cache is a structured system variable
 * name; DEFAULT names the default key cache. The server returns one row per
 * table; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cache-index.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Assigning indexes to a key cache
 *     $cache = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('cache index t key (primary, i), u in hot');
 *     [$cache->toString(), $cache->statement->cache?->value] // => ['CACHE INDEX t INDEX (PRIMARY, i), u IN hot', 'hot']
 */
final class CacheIndex implements Statement
{
    use Snapshot;

    /**
     * @var list<CachedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param list<CachedTable> $tables The tables in written order; at least one
     * @param Name|null $cache The key cache name; null for DEFAULT
     * @throws InvalidConstruction When the list is empty, or a table with partitions is not the only one
     */
    public function __construct(array $tables, public readonly ?Name $cache)
    {
        $this->tables = Check::listOf($tables, CachedTable::class, 'CACHE INDEX names at least one table.', 1);
        foreach ($this->tables as $table) {
            Check::input($table->partitions === null || count($this->tables) === 1, 'A table with partitions is the only table of CACHE INDEX.');
        }
    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $tables = [];
        foreach ($this->tables as $table) {
            $tables[] = [$table, $table->table, null];
        }
        (new TableNames())->record($derivation, $tables);
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CACHE', 'INDEX')->list($this->tables)->keyword('IN');
        if ($this->cache === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->cache, NameUse::Identifier);
        }
    }
}

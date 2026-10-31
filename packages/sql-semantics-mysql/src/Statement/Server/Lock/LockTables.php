<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `LOCK {TABLE | TABLES} t [[AS] alias] lock_type, …`: a request to lock tables for the session.
 *
 * Mirrors SQLCOM_LOCK_TABLES. Rule: MYSQL-LOCK-TABLES-001. Each table name
 * resolves by MYSQL-SERVER-TABLES-001 and its resolution is the relation
 * fact of its TableLock; an alias or name used twice is NonUniqueTable. The
 * statement releases the locks the session held before. TABLE and TABLES
 * are synonyms; the written keyword is retained.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Locking two tables
 *     $lock = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('lock table t read local, u as v write');
 *     [$lock->toString(), $lock->locks[1]->alias?->value] // => ['LOCK TABLE t READ LOCAL, u AS v WRITE', 'v']
 */
final class LockTables extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<TableLock> The locked tables in written order; at least one
     */
    public readonly array $locks;

    /**
     * @param list<TableLock> $locks The locked tables in written order; at least one
     * @throws InvalidConstruction When the list is empty
     */
    public function __construct(array $locks, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Table\TableWord $tableWord = \SqlSemantics\Platform\MySql\Statement\Table\TableWord::Tables)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->locks = Check::listOf($locks, TableLock::class, 'LOCK TABLES locks at least one table.', 1);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\From\RelationNames())->repeatedTables(array_map(static fn (TableLock $lock): array => [$lock->table, $lock->alias, $lock], $this->locks)) === null, 'LOCK TABLES names each table once.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the resolution of each table and reports a repeated alias; each LOW_PRIORITY WRITE warns that it is deprecated.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $tables = [];
        foreach ($this->locks as $lock) {
            $tables[] = [$lock, $lock->table, $lock->alias];
            if ($lock->mode === LockMode::LowPriorityWrite) {
                \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::LowPriorityWrite, $derivation);
            }
        }
        (new TableNames())->record($derivation, $tables);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOCK', $this->tableWord->value)->list($this->locks);
    }
}

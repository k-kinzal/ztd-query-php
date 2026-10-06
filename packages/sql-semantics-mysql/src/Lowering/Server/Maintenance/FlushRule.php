<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Statement\Statement;

/**
 * Lowers FLUSH.
 *
 * Rule: MYSQL-FLUSH-001. Scope: flush, flush_options, opt_flush_lock,
 * flush_options_list, flush_option. FLUSH TABLES with its optional table
 * list and lock is one statement, the other items another. FOR EXPORT
 * without a table list is a syntax error the server raises in the
 * `opt_flush_lock` action (ER_NO_TABLES_USED as a parse error) and is
 * rejected as such. The channel of RELAY LOGS goes through the replication
 * family's channel rule. TABLE and TABLES are synonyms and LOCAL is
 * NO_WRITE_TO_BINLOG (LeafNoise). Constructs: Flush, FlushItem,
 * FlushTables. Terminates: the item list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class FlushRule
{
    /**
     * The item productions, by what they flush.
     */
    private const OPTIONS = [
        'flush_option: ERROR_SYM LOGS_SYM' => FlushOption::ErrorLogs, 'flush_option: ENGINE_SYM LOGS_SYM' => FlushOption::EngineLogs,
        'flush_option: GENERAL LOGS_SYM' => FlushOption::GeneralLogs, 'flush_option: SLOW LOGS_SYM' => FlushOption::SlowLogs,
        'flush_option: BINARY LOGS_SYM' => FlushOption::BinaryLogs, 'flush_option: BINARY_SYM LOGS_SYM' => FlushOption::BinaryLogs,
        'flush_option: RELAY LOGS_SYM' => FlushOption::RelayLogs, 'flush_option: QUERY_SYM CACHE_SYM' => FlushOption::QueryCache,
        'flush_option: HOSTS_SYM' => FlushOption::Hosts, 'flush_option: PRIVILEGES' => FlushOption::Privileges, 'flush_option: LOGS_SYM' => FlushOption::Logs,
        'flush_option: STATUS_SYM' => FlushOption::Status, 'flush_option: DES_KEY_FILE' => FlushOption::DesKeyFile,
        'flush_option: RESOURCES' => FlushOption::Resources, 'flush_option: OPTIMIZER_COSTS_SYM' => FlushOption::OptimizerCosts,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers FLUSH: a node of `flush`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When FOR EXPORT has no table list
     */
    public function statement(Form $form): Statement
    {
        if ($form->signature !== 'flush: FLUSH_SYM opt_no_write_to_binlog flush_options') {
            throw ImplementationGap::production($form);
        }
        $noWrite = $this->lowering->options->present($form->node(1));
        $options = $this->lowering->form($form->node(2));

        return match ($options->signature) {
            'flush_options: table_or_tables opt_table_list opt_flush_lock' => $this->tables($noWrite, $options),
            'flush_options: flush_options_list' => new Flush($noWrite, $this->items($options->node(0))),
            default => throw ImplementationGap::production($options),
        };
    }

    /**
     * Lowers FLUSH TABLES.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When FOR EXPORT has no table list
     */
    public function tables(bool $noWriteToBinlog, Form $form): FlushTables
    {
        $this->lowering->options->skip($form->node(0));
        $tables = [];
        foreach ($this->lowering->names->qualifiedList($form->node(1)) as $name) {
            $tables[] = new MaintainedTable($name);
        }
        $lock = $this->lowering->form($form->node(2));
        $mode = match ($lock->signature) {
            'opt_flush_lock:' => null,
            'opt_flush_lock: WITH READ_SYM LOCK_SYM' => FlushLock::WithReadLock,
            'opt_flush_lock: FOR_SYM EXPORT_SYM' => FlushLock::ForExport,
            default => throw ImplementationGap::production($lock),
        };
        if ($mode === FlushLock::ForExport && $tables === []) {
            throw new AnalysisException('Syntax error: FLUSH TABLES ... FOR EXPORT names the tables (no tables used).');
        }

        return new FlushTables($noWriteToBinlog, $tables, $mode);
    }

    /**
     * Lowers the items of FLUSH: a node of `flush_options_list`.
     *
     * @return list<FlushItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $this->lowering->names->claimed($this->lowering->form($list), ['flush_options_list: flush_options_list , flush_option', 'flush_options_list: flush_option']);
        $items = [];
        foreach ((new Lists())->items($list) as $item) {
            $form = $this->lowering->form($item);
            $items[] = $form->signature === 'flush_option: RELAY LOGS_SYM opt_channel'
                ? new FlushItem(FlushOption::RelayLogs, $this->lowering->replication->channel($form->node(2)))
                : new FlushItem(self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form));
        }

        return $items;
    }
}

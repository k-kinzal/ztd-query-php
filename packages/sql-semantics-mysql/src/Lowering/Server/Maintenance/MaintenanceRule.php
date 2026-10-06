<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\HistogramUpdate;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the table maintenance statements and their options.
 *
 * Rule: MYSQL-MAINTENANCE-001. Scope: analyze, check, optimize, repair (5.6,
 * 5.7), analyze_table_stmt, check_table_stmt, optimize_table_stmt,
 * repair_table_stmt (8.0 and later), checksum, opt_checksum_type,
 * opt_mi_check_type, opt_mi_check_types, mi_check_types, mi_check_type,
 * opt_mi_repair_type, opt_mi_repair_types, mi_repair_types, mi_repair_type,
 * opt_histogram, opt_histogram_update_param, opt_histogram_num_buckets,
 * opt_histogram_auto_update. TABLE and TABLES are synonyms and LOCAL is
 * NO_WRITE_TO_BINLOG (LeafNoise). The options are kept in written order. The
 * bucket count is a NUM token, kept as written. Constructs: AnalyzeTable,
 * CheckTable, ChecksumTable, OptimizeTable, RepairTable, MaintainedTable,
 * UpdateHistogram, LoadHistogram, DropHistogram. Terminates: lists are
 * flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/table-maintenance-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class MaintenanceRule
{
    /**
     * The check option productions, by the option they select.
     */
    private const CHECKS = [
        'mi_check_type: QUICK' => CheckOption::Quick, 'mi_check_type: FAST_SYM' => CheckOption::Fast, 'mi_check_type: MEDIUM_SYM' => CheckOption::Medium,
        'mi_check_type: EXTENDED_SYM' => CheckOption::Extended, 'mi_check_type: CHANGED' => CheckOption::Changed,
        'mi_check_type: FOR_SYM UPGRADE_SYM' => CheckOption::ForUpgrade,
    ];

    /**
     * The repair option productions, by the option they select.
     */
    private const REPAIRS = ['mi_repair_type: QUICK' => RepairOption::Quick, 'mi_repair_type: EXTENDED_SYM' => RepairOption::Extended, 'mi_repair_type: USE_FRM' => RepairOption::UseFrm];

    /**
     * The productions of option lists: absent lists, unit productions and list spines.
     */
    private const LISTS = [
        'opt_mi_check_type:' => null, 'opt_mi_check_types:' => null, 'opt_mi_repair_type:' => null, 'opt_mi_repair_types:' => null,
        'opt_mi_check_type: mi_check_types' => 0, 'opt_mi_check_types: mi_check_types' => 0, 'opt_mi_repair_type: mi_repair_types' => 0,
        'opt_mi_repair_types: mi_repair_types' => 0, 'mi_check_types: mi_check_type' => -1, 'mi_check_types: mi_check_type mi_check_types' => -1,
        'mi_repair_types: mi_repair_type' => -1, 'mi_repair_types: mi_repair_type mi_repair_types' => -1, 'mi_repair_types: mi_repair_types mi_repair_type' => -1,
    ];

    /**
     * The statement productions: the statement, the position of NO_WRITE_TO_BINLOG, of the table list and of the options.
     */
    private const STATEMENTS = [
        'analyze: ANALYZE_SYM opt_no_write_to_binlog table_or_tables table_list' => ['analyze', 1, 3, null],
        'analyze_table_stmt: ANALYZE_SYM opt_no_write_to_binlog table_or_tables table_list opt_histogram' => ['analyze', 1, 3, 4],
        'check: CHECK_SYM table_or_tables table_list opt_mi_check_type' => ['check', null, 2, 3],
        'check_table_stmt: CHECK_SYM table_or_tables table_list opt_mi_check_types' => ['check', null, 2, 3],
        'optimize: OPTIMIZE opt_no_write_to_binlog table_or_tables table_list' => ['optimize', 1, 3, null],
        'optimize_table_stmt: OPTIMIZE opt_no_write_to_binlog table_or_tables table_list' => ['optimize', 1, 3, null],
        'repair: REPAIR opt_no_write_to_binlog table_or_tables table_list opt_mi_repair_type' => ['repair', 1, 3, 4],
        'repair_table_stmt: REPAIR opt_no_write_to_binlog table_or_tables table_list opt_mi_repair_types' => ['repair', 1, 3, 4],
        'checksum: CHECKSUM_SYM table_or_tables table_list opt_checksum_type' => ['checksum', null, 2, 3],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a table maintenance statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        [$kind, $local, $list, $options] = self::STATEMENTS[$form->signature] ?? throw ImplementationGap::production($form);
        $this->lowering->options->skip($form->node($list - 1));
        $noWrite = $local !== null && $this->lowering->options->present($form->node($local));
        $tables = $this->tables($form->node($list));

        return match ($kind) {
            'analyze' => new AnalyzeTable($noWrite, $tables, $options === null ? null : $this->histogram($form->node($options))),
            'check' => new CheckTable($tables, $this->checkOptions($form->node((int) $options))),
            'optimize' => new OptimizeTable($noWrite, $tables),
            'repair' => new RepairTable($noWrite, $tables, $this->repairOptions($form->node((int) $options))),
            'checksum' => new ChecksumTable($tables, $this->checksum($form->node((int) $options))),
        };
    }

    /**
     * Lowers a table list: a node of `table_list`.
     *
     * @return list<MaintainedTable>
     * @throws ImplementationGap When a production has no rule
     */
    public function tables(Node $list): array
    {
        $tables = [];
        foreach ($this->lowering->names->qualifiedList($list) as $name) {
            $tables[] = new MaintainedTable($name);
        }

        return $tables;
    }

    /**
     * Lowers the options of a table check: a node of `opt_mi_check_type` or `opt_mi_check_types`.
     *
     * @return list<CheckOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function checkOptions(Node $options): array
    {
        $lowered = [];
        foreach ($this->options($options) as $option) {
            $lowered[] = self::CHECKS[$option->signature] ?? throw ImplementationGap::production($option);
        }

        return $lowered;
    }

    /**
     * Lowers the options of a table repair: a node of `opt_mi_repair_type` or `opt_mi_repair_types`.
     *
     * @return list<RepairOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function repairOptions(Node $options): array
    {
        $lowered = [];
        foreach ($this->options($options) as $option) {
            $lowered[] = self::REPAIRS[$option->signature] ?? throw ImplementationGap::production($option);
        }

        return $lowered;
    }

    /**
     * Answers the option productions of an optional option list, in written order.
     *
     * @return list<Form>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->form($options);
        if (!array_key_exists($form->signature, self::LISTS)) {
            throw ImplementationGap::production($form);
        }
        if (self::LISTS[$form->signature] === null) {
            return [];
        }
        $spine = $form->node(0);
        $forms = [];
        foreach ((new Lists())->items($spine) as $item) {
            $forms[] = $this->lowering->form($item);
        }
        $this->lowering->names->claimed($this->lowering->form($spine), array_keys(array_filter(self::LISTS, static fn (?int $kind): bool => $kind === -1)));

        return $forms;
    }

    /**
     * Lowers the method of CHECKSUM TABLE: a node of `opt_checksum_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function checksum(Node $mode): ?ChecksumMode
    {
        $form = $this->lowering->form($mode);

        return match ($form->signature) {
            'opt_checksum_type:' => null,
            'opt_checksum_type: QUICK' => ChecksumMode::Quick,
            'opt_checksum_type: EXTENDED_SYM' => ChecksumMode::Extended,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the histogram request of ANALYZE TABLE: a node of `opt_histogram`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function histogram(Node $histogram): ?Histogram
    {
        $form = $this->lowering->form($histogram);

        return match ($form->signature) {
            'opt_histogram:' => null,
            'opt_histogram: UPDATE_SYM HISTOGRAM_SYM ON_SYM ident_string_list opt_histogram_update_param' => $this->update($this->lowering->names->identifiers($form->node(3)), $form->node(4)),
            'opt_histogram: DROP HISTOGRAM_SYM ON_SYM ident_string_list' => new DropHistogram($this->lowering->names->identifiers($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers UPDATE HISTOGRAM from its columns and its parameters: a node of `opt_histogram_update_param`.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $columns
     * @throws ImplementationGap When a production has no rule
     */
    public function update(array $columns, Node $parameters): UpdateHistogram|LoadHistogram
    {
        $form = $this->lowering->form($parameters);

        return match ($form->signature) {
            'opt_histogram_update_param:' => new UpdateHistogram($columns),
            'opt_histogram_update_param: WITH NUM BUCKETS_SYM' => new UpdateHistogram($columns, $this->buckets($form)),
            'opt_histogram_update_param: USING DATA_SYM TEXT_STRING_literal' => new LoadHistogram($columns, $this->lowering->literals->text($form->node(2))),
            'opt_histogram_update_param: opt_histogram_num_buckets opt_histogram_auto_update' => new UpdateHistogram(
                $columns,
                $this->optionalBuckets($form->node(0)),
                $this->automatic($form->node(1)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional bucket count of MySQL 8.4 and later: a node of `opt_histogram_num_buckets`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function optionalBuckets(Node $buckets): ?Numeral
    {
        $form = $this->lowering->form($buckets);

        return match ($form->signature) {
            'opt_histogram_num_buckets:' => null,
            'opt_histogram_num_buckets: WITH NUM BUCKETS_SYM' => $this->buckets($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the NUM token of `WITH NUM BUCKETS`.
     */
    public function buckets(Form $form): Numeral
    {
        return $this->lowering->numbers->token($form->token(1));
    }

    /**
     * Lowers MANUAL UPDATE or AUTO UPDATE: a node of `opt_histogram_auto_update`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function automatic(Node $update): ?HistogramUpdate
    {
        $form = $this->lowering->form($update);

        return match ($form->signature) {
            'opt_histogram_auto_update:' => null,
            'opt_histogram_auto_update: MANUAL_SYM UPDATE_SYM' => HistogramUpdate::Manual,
            'opt_histogram_auto_update: AUTO_SYM UPDATE_SYM' => HistogramUpdate::Automatic,
            default => throw ImplementationGap::production($form),
        };
    }
}

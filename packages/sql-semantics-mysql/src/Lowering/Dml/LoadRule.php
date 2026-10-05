<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * Lowers LOAD DATA and LOAD XML of every release.
 *
 * Rule: MYSQL-LOAD-LOWERING-001. Scope: load (5.6, 5.7), load_stmt (8.0 and
 * later), data_or_xml, load_data_lock, opt_local, opt_from_keyword,
 * load_source_type, opt_source_count, opt_source_order,
 * opt_compression_algorithm, opt_xml_rows_identified_by, opt_ignore_lines,
 * lines_or_rows, opt_field_or_var_spec, fields_or_vars, field_or_var,
 * opt_load_data_set_spec, load_data_set_list, load_data_set_elem,
 * opt_load_parallel, opt_load_memory, opt_load_algorithm. The parts of the
 * statement are found by their nonterminal, each of which occurs once in
 * every production. A number that the grammar reads as a bare NUM token is
 * a Numeral of its digits. The word before the file count must be COUNT, as
 * the server's grammar action requires. Terminates: the parts are strict
 * subtrees; lists are flattened by MYSQL-DML-LIST-001. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-xml.html, sql/sql_yacc.yy 8.4. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class LoadRule
{
    /**
     * The productions of LOAD.
     */
    private const STATEMENTS = [
        'load: LOAD data_or_xml load_data_lock opt_local INFILE TEXT_STRING_filesystem opt_duplicate INTO TABLE_SYM table_ident opt_use_partition opt_load_data_charset opt_xml_rows_identified_by opt_field_term opt_line_term opt_ignore_lines opt_field_or_var_spec opt_load_data_set_spec' => true,
        'load_stmt: LOAD data_or_xml load_data_lock opt_from_keyword opt_local load_source_type TEXT_STRING_filesystem opt_source_count opt_source_order opt_duplicate INTO TABLE_SYM table_ident opt_use_partition opt_load_data_charset opt_xml_rows_identified_by opt_field_term opt_line_term opt_ignore_lines opt_field_or_var_spec opt_load_data_set_spec opt_load_algorithm' => true,
        'load_stmt: LOAD data_or_xml load_data_lock opt_from_keyword opt_local load_source_type TEXT_STRING_filesystem opt_source_count opt_source_order opt_duplicate INTO TABLE_SYM table_ident opt_use_partition opt_load_data_charset opt_xml_rows_identified_by opt_field_term opt_line_term opt_ignore_lines opt_field_or_var_spec opt_load_data_set_spec opt_load_parallel opt_load_memory opt_load_algorithm' => true,
        'load_stmt: LOAD data_or_xml load_data_lock opt_from_keyword opt_local load_source_type TEXT_STRING_filesystem opt_source_count opt_source_order opt_duplicate INTO TABLE_SYM table_ident opt_use_partition opt_load_data_charset opt_compression_algorithm opt_xml_rows_identified_by opt_field_term opt_line_term opt_ignore_lines opt_field_or_var_spec opt_load_data_set_spec opt_load_parallel opt_load_memory opt_load_algorithm' => true,
    ];

    /**
     * The formats by production.
     */
    private const FORMATS = ['data_or_xml: DATA_SYM' => LoadFormat::Data, 'data_or_xml: XML_SYM' => LoadFormat::Xml];

    /**
     * The scheduling modifiers by production.
     */
    private const LOCKS = ['load_data_lock:' => null, 'load_data_lock: CONCURRENT' => LoadLock::Concurrent, 'load_data_lock: LOW_PRIORITY' => LoadLock::LowPriority];

    /**
     * The sources by production.
     */
    private const SOURCES = ['load_source_type: INFILE_SYM' => LoadSource::Infile, 'load_source_type: URL_SYM' => LoadSource::Url, 'load_source_type: S3_SYM' => LoadSource::S3];

    /**
     * The optional words by production: whether the word is written.
     */
    private const FLAGS = [
        'opt_local:' => false, 'opt_local: LOCAL_SYM' => true, 'opt_source_order:' => false, 'opt_source_order: IN_SYM PRIMARY_SYM KEY_SYM ORDER_SYM' => true,
        'opt_load_algorithm:' => false, 'opt_load_algorithm: ALGORITHM_SYM EQ BULK_SYM' => true, 'opt_from_keyword:' => false, 'opt_from_keyword: FROM' => true,
        'lines_or_rows: LINES' => true, 'lines_or_rows: ROWS_SYM' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `load` or `load_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the word before the number of files is not COUNT
     */
    public function statement(Form $form): LoadTable
    {
        if (!isset(self::STATEMENTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $parts = [];
        foreach ($form->node->children as $child) {
            if ($child instanceof Node) {
                $parts[$child->name] = $child;
            }
        }
        $this->flag($parts['opt_from_keyword'] ?? null);
        $format = new FormatRule($this->lowering);
        $queries = $this->lowering->queries;

        return new LoadTable(
            $this->format($parts['data_or_xml']),
            $this->lock($parts['load_data_lock']),
            $this->input($parts),
            $this->lowering->dml->duplicateHandling($parts['opt_duplicate']),
            new WriteTarget($this->lowering->names->qualified($parts['table_ident']), null, $queries->partitions($parts['opt_use_partition'])),
            $format->charset($parts['opt_load_data_charset']),
            $this->tagged($parts['opt_compression_algorithm'] ?? null),
            $this->tagged($parts['opt_xml_rows_identified_by']),
            $format->fields($parts['opt_field_term']),
            $format->lines($parts['opt_line_term']),
            $this->ignored($parts['opt_ignore_lines']),
            $this->columns($parts['opt_field_or_var_spec']),
            $this->assignments($parts['opt_load_data_set_spec']),
            $this->bulk($parts),
        );
    }

    /**
     * Lowers a node of `data_or_xml`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function format(Node $node): LoadFormat
    {
        $form = $this->lowering->form($node);

        return self::FORMATS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers a node of `load_data_lock`; no modifier is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lock(Node $node): ?LoadLock
    {
        $form = $this->lowering->form($node);
        if (!array_key_exists($form->signature, self::LOCKS)) {
            throw ImplementationGap::production($form);
        }

        return self::LOCKS[$form->signature];
    }

    /**
     * Lowers a node of `load_source_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function source(Node $node): LoadSource
    {
        $form = $this->lowering->form($node);

        return self::SOURCES[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers an optional word: whether it is written; an absent part is not.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function flag(?Node $node): bool
    {
        if ($node === null) {
            return false;
        }
        $form = $this->lowering->form($node);

        return self::FLAGS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers what is read: LOCAL, the source kind, the file, COUNT and IN PRIMARY KEY ORDER.
     *
     * @param array<string, Node> $parts
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the word before the number of files is not COUNT
     */
    public function input(array $parts): LoadInput
    {
        $source = isset($parts['load_source_type']) ? $this->source($parts['load_source_type']) : LoadSource::Infile;
        $file = $this->lowering->literals->text($parts['TEXT_STRING_filesystem']);
        $local = $this->flag($parts['opt_local']);
        $order = $this->flag($parts['opt_source_order'] ?? null);
        $count = isset($parts['opt_source_count']) ? $this->lowering->form($parts['opt_source_count']) : null;
        if ($count === null || $count->signature === 'opt_source_count:') {
            return new LoadInput($local, $source, $file, null, $order);
        }
        $number = $this->lowering->numbers->token($count->token(1));
        if ($count->signature === 'opt_source_count: COUNT_SYM NUM') {
            return new LoadInput($local, $source, $file, $number, $order);
        }
        if ($count->signature !== 'opt_source_count: IDENT_sys NUM') {
            throw ImplementationGap::production($count);
        }
        $word = $this->lowering->names->identifier($count->node(0));
        if (strcasecmp($word->value, 'count') !== 0) {
            throw new AnalysisException('Syntax error: COUNT expected before the number of files.');
        }

        return new LoadInput($local, $source, $file, $number, $order, $word);
    }

    /**
     * Lowers a node of `opt_compression_algorithm` or `opt_xml_rows_identified_by`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function tagged(?Node $clause): ?Text
    {
        if ($clause === null) {
            return null;
        }
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_compression_algorithm:', 'opt_xml_rows_identified_by:' => null,
            'opt_compression_algorithm: COMPRESSION_SYM EQ TEXT_STRING_sys', 'opt_xml_rows_identified_by: ROWS_SYM IDENTIFIED_SYM BY text_string' => $this->lowering->literals->text($form->node(count($form->node->children) - 1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a node of `opt_ignore_lines`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function ignored(Node $clause): ?Numeral
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_ignore_lines:') {
            return null;
        }
        if ($form->signature !== 'opt_ignore_lines: IGNORE_SYM NUM lines_or_rows') {
            throw ImplementationGap::production($form);
        }
        $this->flag($form->node(2));

        return $this->lowering->numbers->token($form->token(1));
    }

    /**
     * Lowers a node of `opt_field_or_var_spec`; an absent or empty list is empty.
     *
     * @return list<ColumnUse|UserVariable>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $spec): array
    {
        $form = $this->lowering->form($spec);
        if ($form->signature === 'opt_field_or_var_spec:' || $form->signature === 'opt_field_or_var_spec: ( )') {
            return [];
        }
        if ($form->signature !== 'opt_field_or_var_spec: ( fields_or_vars )') {
            throw ImplementationGap::production($form);
        }
        $columns = [];
        foreach ((new ListRule($this->lowering))->items($form->node(1), ['fields_or_vars: fields_or_vars , field_or_var', 'fields_or_vars: field_or_var']) as $item) {
            $column = $this->lowering->form($item);
            $columns[] = match ($column->signature) {
                'field_or_var: simple_ident_nospvar' => $this->lowering->names->column($column->node(0)),
                'field_or_var: @ ident_or_text' => $this->lowering->variables->user($column->node(1)),
                default => throw ImplementationGap::production($column),
            };
        }

        return $columns;
    }

    /**
     * Lowers a node of `opt_load_data_set_spec`; an absent clause is empty.
     *
     * @return list<Assignment>
     * @throws ImplementationGap When a production has no rule
     */
    public function assignments(Node $spec): array
    {
        $form = $this->lowering->form($spec);
        if ($form->signature === 'opt_load_data_set_spec:') {
            return [];
        }
        if ($form->signature !== 'opt_load_data_set_spec: SET load_data_set_list' && $form->signature !== 'opt_load_data_set_spec: SET_SYM load_data_set_list') {
            throw ImplementationGap::production($form);
        }
        $values = new ValueRule($this->lowering);
        $assignments = [];
        foreach ((new ListRule($this->lowering))->items($form->node(1), ['load_data_set_list: load_data_set_list , load_data_set_elem', 'load_data_set_list: load_data_set_elem']) as $item) {
            $element = $this->lowering->form($item);
            if ($element->signature === 'load_data_set_elem: simple_ident_nospvar equal remember_name expr_or_default remember_end') {
                $this->lowering->options->skip($element->node(1));
                $this->lowering->options->skip($element->node(2));
                $this->lowering->options->skip($element->node(4));
                $assignments[] = new Assignment($this->lowering->names->column($element->node(0)), $values->value($element->node(3)));
                continue;
            }
            if ($element->signature !== 'load_data_set_elem: simple_ident_nospvar equal expr_or_default') {
                throw ImplementationGap::production($element);
            }
            $this->lowering->options->skip($element->node(1));
            $assignments[] = new Assignment($this->lowering->names->column($element->node(0)), $values->value($element->node(2)));
        }

        return $assignments;
    }

    /**
     * Lowers the options of a bulk load; none is null.
     *
     * @param array<string, Node> $parts
     * @throws ImplementationGap When a production has no rule
     */
    public function bulk(array $parts): ?BulkOptions
    {
        $parallel = null;
        $memory = null;
        if (isset($parts['opt_load_parallel'])) {
            $form = $this->lowering->form($parts['opt_load_parallel']);
            $parallel = match ($form->signature) {
                'opt_load_parallel:' => null,
                'opt_load_parallel: PARALLEL_SYM EQ NUM' => $this->lowering->numbers->token($form->token(2)),
                default => throw ImplementationGap::production($form),
            };
        }
        if (isset($parts['opt_load_memory'])) {
            $form = $this->lowering->form($parts['opt_load_memory']);
            $memory = match ($form->signature) {
                'opt_load_memory:' => null,
                'opt_load_memory: MEMORY_SYM EQ size_number' => $this->lowering->numbers->size($form->node(2)),
                default => throw ImplementationGap::production($form),
            };
        }
        $algorithm = $this->flag($parts['opt_load_algorithm'] ?? null);

        return $parallel === null && $memory === null && !$algorithm ? null : new BulkOptions($parallel, $memory, $algorithm);
    }
}

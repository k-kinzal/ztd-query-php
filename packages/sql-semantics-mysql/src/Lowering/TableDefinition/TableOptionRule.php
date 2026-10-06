<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;
use SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\InsertMethodOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Table\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * Lowers table options.
 *
 * Rule: MYSQL-TABLE-OPTION-001. Scope: opt_create_table_options,
 * create_table_options, create_table_options_space_separated,
 * create_table_option, row_types, merge_insert_types. The equals sign of
 * an option and the comma between options are optional and not kept; the
 * options are kept in written order (a later option of the same kind
 * overrides an earlier one). Constructs: the TableOption classes.
 * Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableOptionRule
{
    /**
     * The option list productions.
     */
    private const LISTS = [
        'create_table_options: create_table_option', 'create_table_options: create_table_option create_table_options',
        'create_table_options: create_table_option , create_table_options', 'create_table_options: create_table_options opt_comma create_table_option',
        'create_table_options_space_separated: create_table_option', 'create_table_options_space_separated: create_table_option create_table_options_space_separated',
        'create_table_options_space_separated: create_table_options_space_separated create_table_option',
    ];

    /**
     * The numeric options: the option and how its value is written (a number, a number or DEFAULT, or DEFAULT).
     */
    private const NUMBERS = [
        'create_table_option: MAX_ROWS opt_equal ulonglong_num' => [NumberOptionKind::MaxRows, 'number'], 'create_table_option: MIN_ROWS opt_equal ulonglong_num' => [NumberOptionKind::MinRows, 'number'],
        'create_table_option: AVG_ROW_LENGTH opt_equal ulong_num' => [NumberOptionKind::AverageRowLength, 'number'],
        'create_table_option: AVG_ROW_LENGTH opt_equal ulonglong_num' => [NumberOptionKind::AverageRowLength, 'number'],
        'create_table_option: AUTO_INC opt_equal ulonglong_num' => [NumberOptionKind::AutoIncrement, 'number'],
        'create_table_option: PACK_KEYS_SYM opt_equal ulong_num' => [NumberOptionKind::PackKeys, 'number'], 'create_table_option: PACK_KEYS_SYM opt_equal DEFAULT' => [NumberOptionKind::PackKeys, 'default'],
        'create_table_option: PACK_KEYS_SYM opt_equal ternary_option' => [NumberOptionKind::PackKeys, 'ternary'],
        'create_table_option: STATS_AUTO_RECALC_SYM opt_equal ulong_num' => [NumberOptionKind::StatsAutoRecalc, 'number'],
        'create_table_option: STATS_AUTO_RECALC_SYM opt_equal DEFAULT' => [NumberOptionKind::StatsAutoRecalc, 'default'],
        'create_table_option: STATS_AUTO_RECALC_SYM opt_equal ternary_option' => [NumberOptionKind::StatsAutoRecalc, 'ternary'],
        'create_table_option: STATS_PERSISTENT_SYM opt_equal ulong_num' => [NumberOptionKind::StatsPersistent, 'number'],
        'create_table_option: STATS_PERSISTENT_SYM opt_equal DEFAULT' => [NumberOptionKind::StatsPersistent, 'default'],
        'create_table_option: STATS_PERSISTENT_SYM opt_equal ternary_option' => [NumberOptionKind::StatsPersistent, 'ternary'],
        'create_table_option: STATS_SAMPLE_PAGES_SYM opt_equal ulong_num' => [NumberOptionKind::StatsSamplePages, 'number'],
        'create_table_option: STATS_SAMPLE_PAGES_SYM opt_equal DEFAULT' => [NumberOptionKind::StatsSamplePages, 'default'],
        'create_table_option: STATS_SAMPLE_PAGES_SYM opt_equal DEFAULT_SYM' => [NumberOptionKind::StatsSamplePages, 'default'],
        'create_table_option: CHECKSUM_SYM opt_equal ulong_num' => [NumberOptionKind::Checksum, 'number'],
        'create_table_option: TABLE_CHECKSUM_SYM opt_equal ulong_num' => [NumberOptionKind::TableChecksum, 'number'],
        'create_table_option: DELAY_KEY_WRITE_SYM opt_equal ulong_num' => [NumberOptionKind::DelayKeyWrite, 'number'],
        'create_table_option: KEY_BLOCK_SIZE opt_equal ulong_num' => [NumberOptionKind::KeyBlockSize, 'number'],
        'create_table_option: KEY_BLOCK_SIZE opt_equal ulonglong_num' => [NumberOptionKind::KeyBlockSize, 'number'],
    ];

    /**
     * The string options: the option and the position of its string.
     */
    private const TEXTS = [
        'create_table_option: PASSWORD opt_equal TEXT_STRING_sys' => [TextOptionKind::Password, 2], 'create_table_option: COMMENT_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::Comment, 2],
        'create_table_option: DATA_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::DataDirectory, 3],
        'create_table_option: INDEX_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::IndexDirectory, 3],
        'create_table_option: CONNECTION_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::Connection, 2],
        'create_table_option: COMPRESSION_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::Compression, 2],
        'create_table_option: ENCRYPTION_SYM opt_equal TEXT_STRING_sys' => [TextOptionKind::Encryption, 2],
        'create_table_option: ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => [TextOptionKind::EngineAttribute, 2],
        'create_table_option: SECONDARY_ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => [TextOptionKind::SecondaryEngineAttribute, 2],
    ];

    /**
     * The row formats and the MERGE insert methods.
     */
    private const KEYWORDS = [
        'row_types: DEFAULT' => RowFormat::Default, 'row_types: DEFAULT_SYM' => RowFormat::Default, 'row_types: FIXED_SYM' => RowFormat::Fixed,
        'row_types: DYNAMIC_SYM' => RowFormat::Dynamic, 'row_types: COMPRESSED_SYM' => RowFormat::Compressed, 'row_types: REDUNDANT_SYM' => RowFormat::Redundant,
        'row_types: COMPACT_SYM' => RowFormat::Compact, 'merge_insert_types: NO_SYM' => InsertMethod::No, 'merge_insert_types: FIRST_SYM' => InsertMethod::First,
        'merge_insert_types: LAST_SYM' => InsertMethod::Last,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a list of table options: a node of `opt_create_table_options`, `create_table_options` or `create_table_options_space_separated`.
     *
     * @return list<TableOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        if ($list->name === 'opt_create_table_options') {
            $form = $this->lowering->productions->form($list);

            return match ($form->signature) {
                'opt_create_table_options:' => [],
                'opt_create_table_options: create_table_options' => $this->options($form->node(0)),
                default => throw ImplementationGap::production($form),
            };
        }
        $options = [];
        foreach ((new Spine($this->lowering))->items($list, self::LISTS, ['create_table_option']) as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers one table option: a node of `create_table_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): TableOption
    {
        $form = $this->lowering->productions->form($option);
        $number = self::NUMBERS[$form->signature] ?? null;
        if ($number !== null) {
            $numbers = $this->lowering->numbers;

            return new NumberOption($number[0], match ($number[1]) {
                'number' => $numbers->numeral($form->node(2)),
                'ternary' => $numbers->ternary($form->node(2)),
                'default' => null,
            });
        }
        $text = self::TEXTS[$form->signature] ?? null;
        if ($text !== null) {
            return new TextOption($text[0], $this->lowering->literals->text($form->node($text[1])));
        }

        return $this->named($form);
    }

    /**
     * Lowers the options whose value is a name or a keyword.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function named(Form $form): TableOption
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'create_table_option: ENGINE_SYM opt_equal storage_engines', 'create_table_option: ENGINE_SYM opt_equal ident_or_text' => new EngineOption($names->identifier($form->node(2))),
            'create_table_option: SECONDARY_ENGINE_SYM opt_equal NULL_SYM' => new SecondaryEngineOption(null),
            'create_table_option: SECONDARY_ENGINE_SYM opt_equal ident_or_text' => new SecondaryEngineOption($names->identifier($form->node(2))),
            'create_table_option: ROW_FORMAT_SYM opt_equal row_types' => new RowFormatOption($this->keyword($form->node(2), RowFormat::class)),
            'create_table_option: INSERT_METHOD opt_equal merge_insert_types' => new InsertMethodOption($this->keyword($form->node(2), InsertMethod::class)),
            'create_table_option: UNION_SYM opt_equal ( opt_table_list )' => new UnionOption($names->qualifiedList($form->node(3))),
            'create_table_option: default_charset' => new CharsetOption($this->lowering->charsets->charset($form->node(0))),
            'create_table_option: default_collation' => new CollationOption($this->lowering->charsets->collation($form->node(0)) ?? throw ImplementationGap::production($form)),
            'create_table_option: TABLESPACE ident' => new TablespaceOption($names->identifier($form->node(1))),
            'create_table_option: TABLESPACE_SYM opt_equal ident' => new TablespaceOption($names->identifier($form->node(2))),
            'create_table_option: STORAGE_SYM DISK_SYM' => new StorageOption(StorageMedium::Disk),
            'create_table_option: STORAGE_SYM MEMORY_SYM' => new StorageOption(StorageMedium::Memory),
            'create_table_option: START_SYM TRANSACTION_SYM' => new StartTransaction(),
            'create_table_option: option_autoextend_size' => new AutoextendSizeOption($this->lowering->numbers->size($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a row format or insert method keyword.
     *
     * @template T of RowFormat|InsertMethod
     * @param class-string<T> $enum The enum the keyword belongs to
     * @return T
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword, string $enum): RowFormat|InsertMethod
    {
        $form = $this->lowering->productions->form($keyword);
        $case = self::KEYWORDS[$form->signature] ?? null;

        return $case instanceof $enum ? $case : throw ImplementationGap::production($form);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Partition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;
use SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the partitioning clause: its method, counts and subpartitioning.
 *
 * Rule: MYSQL-PARTITION-CLAUSE-001. Scope: opt_create_partitioning,
 * opt_partitioning, partitioning, partition_entry, partition,
 * partition_clause, part_type_def, opt_linear, opt_key_algo,
 * part_field_list, part_field_item_list, part_field_item,
 * part_column_list, part_func, sub_part_func, part_func_expr,
 * opt_num_parts, opt_sub_part, sub_part_field_list, sub_part_field_item,
 * opt_num_subparts, opt_name_list, name_list. KEY, HASH, RANGE and LIST
 * over an expression or over columns become the method classes of
 * PT_part_type_def; the parenthesized function of 5.6 and 5.7 is the bit
 * expression it encloses. Constructs: PartitionClause, Subpartitioning,
 * KeyMethod, HashMethod, ExpressionMethod, ColumnsMethod, PartitionEntry.
 * Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class PartitionRule
{
    /**
     * The productions of the method rule, by the method they write and the positions of linear, algorithm and operand.
     */
    private const METHODS = [
        'part_type_def: opt_linear KEY_SYM opt_key_algo ( part_field_list )' => ['key', 0, 2, 4],
        'part_type_def: opt_linear KEY_SYM opt_key_algo ( opt_name_list )' => ['key', 0, 2, 4],
        'opt_sub_part: SUBPARTITION_SYM BY opt_linear KEY_SYM opt_key_algo ( sub_part_field_list ) opt_num_subparts' => ['key', 2, 4, 6],
        'opt_sub_part: SUBPARTITION_SYM BY opt_linear KEY_SYM opt_key_algo ( name_list ) opt_num_subparts' => ['key', 2, 4, 6],
        'part_type_def: opt_linear HASH_SYM part_func' => ['hash', 0, null, 2],
        'part_type_def: opt_linear HASH_SYM ( bit_expr )' => ['hash', 0, null, 3],
        'opt_sub_part: SUBPARTITION_SYM BY opt_linear HASH_SYM sub_part_func opt_num_subparts' => ['hash', 2, null, 4],
        'opt_sub_part: SUBPARTITION_SYM BY opt_linear HASH_SYM ( bit_expr ) opt_num_subparts' => ['hash', 2, null, 5],
        'part_type_def: RANGE_SYM part_func' => ['RANGE', null, null, 1],
        'part_type_def: RANGE_SYM ( bit_expr )' => ['RANGE', null, null, 2],
        'part_type_def: LIST_SYM part_func' => ['LIST', null, null, 1],
        'part_type_def: LIST_SYM ( bit_expr )' => ['LIST', null, null, 2],
        'part_type_def: RANGE_SYM part_column_list' => ['RANGE COLUMNS', null, null, 1],
        'part_type_def: RANGE_SYM COLUMNS ( name_list )' => ['RANGE COLUMNS', null, null, 3],
        'part_type_def: LIST_SYM part_column_list' => ['LIST COLUMNS', null, null, 1],
        'part_type_def: LIST_SYM COLUMNS ( name_list )' => ['LIST COLUMNS', null, null, 3],
    ];

    /**
     * The productions of the clause rules, by the positions of method, partition count, subpartitioning and definitions.
     */
    private const CLAUSES = [
        'partition: BY part_type_def opt_num_parts opt_sub_part part_defs' => [1, 2, 3, 4],
        'partition_clause: PARTITION_SYM BY part_type_def opt_num_parts opt_sub_part opt_part_defs' => [2, 3, 4, 5],
    ];

    /**
     * The column list productions, by whether they hold the list itself.
     */
    private const COLUMN_LISTS = [
        'part_field_list:' => false, 'part_field_list: part_field_item_list' => true, 'opt_name_list:' => false, 'opt_name_list: name_list' => true,
        'part_column_list: COLUMNS ( part_field_list )' => true,
    ];

    /**
     * The list spines of column names, with the production of the item when it wraps the name.
     */
    private const COLUMN_ITEMS = [
        'part_field_item_list: part_field_item' => 'part_field_item: ident', 'part_field_item_list: part_field_item_list , part_field_item' => 'part_field_item: ident',
        'sub_part_field_list: sub_part_field_item' => 'sub_part_field_item: ident', 'sub_part_field_list: sub_part_field_list , sub_part_field_item' => 'sub_part_field_item: ident',
        'name_list: ident' => null, 'name_list: name_list , ident' => null,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an optional or required partitioning clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function partitioning(Node $clause): ?PartitionClause
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_create_partitioning: opt_partitioning', 'opt_partitioning: partitioning' => $this->partitioning($form->node(0)),
            'opt_partitioning:' => null,
            'partitioning: PARTITION_SYM have_partitioning partition' => $this->marked($form),
            'partitioning: PARTITION_SYM partition' => $this->clause($form->node(1)),
            'partition_clause: PARTITION_SYM BY part_type_def opt_num_parts opt_sub_part opt_part_defs' => $this->clause($clause),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the 5.6 partitioning production that holds the partitioning marker.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function marked(Form $form): PartitionClause
    {
        $this->lowering->options->skip($form->node(1));

        return $this->clause($form->node(2));
    }

    /**
     * Lowers the statement that is only a partitioning clause: a node of `partition_entry`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function entry(Node $entry): PartitionEntry
    {
        $form = $this->lowering->form($entry);
        if ($form->signature !== 'partition_entry: PARTITION_SYM partition') {
            throw ImplementationGap::production($form);
        }

        return new PartitionEntry($this->clause($form->node(1)));
    }

    /**
     * Lowers the method, counts, subpartitioning and definitions: a node of `partition` or `partition_clause`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function clause(Node $clause): PartitionClause
    {
        $form = $this->lowering->form($clause);
        $positions = self::CLAUSES[$form->signature] ?? throw ImplementationGap::production($form);

        return new PartitionClause(
            $this->method($this->lowering->form($form->node($positions[0]))),
            $this->count($form->node($positions[1])),
            $this->subpartitioning($form->node($positions[2])),
            (new DefinitionRule($this->lowering))->definitions($form->node($positions[3])),
        );
    }

    /**
     * Lowers a method production of `part_type_def` or `opt_sub_part`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function method(Form $form): PartitionMethod
    {
        [$kind, $linear, $algorithm, $operand] = self::METHODS[$form->signature] ?? throw ImplementationGap::production($form);
        $isLinear = $linear !== null && $this->linear($form->node($linear));

        return match ($kind) {
            'key' => new KeyMethod($isLinear, $algorithm === null ? null : $this->algorithm($form->node($algorithm)), $this->columns($form->node($operand))),
            'hash' => new HashMethod($isLinear, $this->expression($form->node($operand))),
            'RANGE', 'LIST' => new ExpressionMethod(PartitionKind::from($kind), $this->expression($form->node($operand))),
            'RANGE COLUMNS' => new ColumnsMethod(PartitionKind::Range, $this->columns($form->node($operand))),
            'LIST COLUMNS' => new ColumnsMethod(PartitionKind::List, $this->columns($form->node($operand))),
        };
    }

    /**
     * Lowers the subpartitioning: a node of `opt_sub_part`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function subpartitioning(Node $clause): ?Subpartitioning
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_sub_part:') {
            return null;
        }

        return new Subpartitioning($this->method($form), $this->count($form->node(count($form->node->children) - 1)));
    }

    /**
     * Lowers LINEAR: a node of `opt_linear`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function linear(Node $linear): bool
    {
        $form = $this->lowering->form($linear);

        return match ($form->signature) {
            'opt_linear:' => false,
            'opt_linear: LINEAR_SYM' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the ALGORITHM of a key method: a node of `opt_key_algo`; an absent option is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function algorithm(Node $algorithm): ?Numeral
    {
        $form = $this->lowering->form($algorithm);

        return match ($form->signature) {
            'opt_key_algo:' => null,
            'opt_key_algo: ALGORITHM_SYM EQ real_ulong_num' => $this->lowering->numbers->numeral($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a PARTITIONS or SUBPARTITIONS count: a node of `opt_num_parts` or `opt_num_subparts`; no count is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function count(Node $count): ?Numeral
    {
        $form = $this->lowering->form($count);

        return match ($form->signature) {
            'opt_num_parts:', 'opt_num_subparts:' => null,
            'opt_num_parts: PARTITIONS_SYM real_ulong_num', 'opt_num_subparts: SUBPARTITIONS_SYM real_ulong_num' => $this->lowering->numbers->numeral($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a partitioning function: a node of `part_func`, `sub_part_func`, `part_func_expr` or `bit_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $function): Scalar
    {
        if ($function->name === 'bit_expr') {
            return $this->lowering->expressions->bitExpression($function);
        }
        $form = $this->lowering->form($function);

        return match ($form->signature) {
            'part_func: ( remember_name part_func_expr remember_end )', 'sub_part_func: ( remember_name part_func_expr remember_end )' => $this->remembered($form),
            'part_func: ( part_func_expr )', 'sub_part_func: ( part_func_expr )' => $this->expression($form->node(1)),
            'part_func_expr: bit_expr' => $this->lowering->expressions->bitExpression($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the 5.6 partitioning function that the source position markers enclose.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function remembered(Form $form): Scalar
    {
        $this->lowering->options->skip($form->node(1));
        $this->lowering->options->skip($form->node(3));

        return $this->expression($form->node(2));
    }

    /**
     * Lowers a list of partitioning column names; an absent list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $form = $this->lowering->form($list);
        $holds = self::COLUMN_LISTS[$form->signature] ?? null;
        if ($holds === false) {
            return [];
        }
        if ($holds === true) {
            return $this->columns($form->node(count($form->node->children) === 1 ? 0 : 2));
        }
        if (!array_key_exists($form->signature, self::COLUMN_ITEMS)) {
            throw ImplementationGap::production($form);
        }
        $names = [];
        foreach ((new Lists())->elements($list) as $element) {
            if ($element instanceof Node) {
                $names[] = $this->column($element);
            }
        }

        return $names;
    }

    /**
     * Lowers one name of a partitioning column list: a node of `ident`, `part_field_item` or `sub_part_field_item`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $item): Name
    {
        if ($item->name === 'ident') {
            return $this->lowering->names->identifier($item);
        }
        $form = $this->lowering->form($item);
        if (!in_array($form->signature, self::COLUMN_ITEMS, true)) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->identifier($form->node(0));
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;

/**
 * Lowers the key part lists of indexes and foreign keys.
 *
 * Rule: MYSQL-KEY-PART-001. Scope: key_list, key_part,
 * key_list_with_expression, key_part_with_expression. In MySQL 5.x the sort
 * direction follows the key part in the list production; in 8.0 it belongs
 * to the key part. A prefix length is kept as written. Constructs:
 * ColumnPart, ExpressionPart. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class KeyPartRule
{
    /**
     * The list productions of every release.
     */
    private const LISTS = [
        'key_list: key_list , key_part order_dir', 'key_list: key_part order_dir', 'key_list: key_list , key_part opt_ordering_direction',
        'key_list: key_part opt_ordering_direction', 'key_list: key_list , key_part', 'key_list: key_part',
        'key_list_with_expression: key_list_with_expression , key_part_with_expression', 'key_list_with_expression: key_part_with_expression',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a key part list: a node of `key_list` or `key_list_with_expression`.
     *
     * @return non-empty-list<KeyPart>
     * @throws ImplementationGap When a production has no rule
     */
    public function parts(Node $list): array
    {
        $parts = [];
        $last = null;
        foreach ((new Spine($this->lowering))->items($list, self::LISTS, ['key_part', 'key_part_with_expression', 'order_dir', 'opt_ordering_direction']) as $item) {
            if ($item->name === 'order_dir' || $item->name === 'opt_ordering_direction') {
                Check::invariant($last !== null, 'A list direction follows its key part.');
                $parts[] = $this->part($last, $item);
                $last = null;
            } else {
                if ($last !== null) {
                    $parts[] = $this->part($last);
                }
                $last = $item;
            }
        }
        if ($last !== null) {
            $parts[] = $this->part($last);
        }
        Check::invariant($parts !== [], 'A key part list has at least one part.');

        return $parts;
    }

    /**
     * Lowers the columns of a foreign key: a node of `key_list`.
     *
     * @return non-empty-list<ColumnPart>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $columns = [];
        foreach ($this->parts($list) as $part) {
            Check::invariant($part instanceof ColumnPart, 'A key_list holds column parts only.');
            $columns[] = $part;
        }

        return $columns;
    }

    /**
     * Lowers one key part: a node of `key_part` or `key_part_with_expression`, with the direction the 5.x list writes after it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function part(Node $part, ?Node $direction = null): KeyPart
    {
        $form = $this->lowering->productions->form($part);
        $queries = $this->lowering->queries;
        $names = $this->lowering->names;

        return match ($form->signature) {
            'key_part_with_expression: key_part' => $this->part($form->node(0), $direction),
            'key_part_with_expression: ( expr ) opt_ordering_direction' => new ExpressionPart($this->lowering->expressions->expression($form->node(1)), $queries->direction($form->node(3))),
            'key_part: ident' => new ColumnPart($names->identifier($form->node(0)), null, $direction === null ? null : $queries->direction($direction)),
            'key_part: ident ( NUM )' => new ColumnPart($names->identifier($form->node(0)), $this->length($part), $direction === null ? null : $queries->direction($direction)),
            'key_part: ident opt_ordering_direction' => new ColumnPart($names->identifier($form->node(0)), null, $queries->direction($form->node(1))),
            'key_part: ident ( NUM ) opt_ordering_direction' => new ColumnPart($names->identifier($form->node(0)), $this->length($part), $queries->direction($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the prefix length of a key part: the number token at the third position.
     */
    public function length(Node $part): Numeral
    {
        return $this->lowering->numbers->token($this->lowering->productions->form($part)->token(2));
    }
}

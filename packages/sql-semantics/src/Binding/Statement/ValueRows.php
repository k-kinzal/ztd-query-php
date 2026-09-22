<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;

/**
 * Rejects syntactically valid VALUES lists with incompatible row widths.
 * @visibility SqlSemantics
 */
final class ValueRows
{
    /**
     * @template T
     * @param non-empty-list<list<T>> $rows
     * @throws \SqlSemantics\InvalidSql
     */
    public static function check(array $rows, Node $source): void
    {
        $width = count($rows[0]);
        foreach ($rows as $row) {
            if (count($row) !== $width) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ValuesWidth, $source);
            }
        }
    }

    /**
     * @return list<list<Node>>
     */
    public static function nodes(Node $statement): array
    {
        $rows = [];
        foreach (\SqlSemantics\Ast\Tree::outer($statement, ['values_clause', 'merge_values_clause', 'mvalues', 'values', 'values_list', 'table_value_constructor']) as $node) {
            foreach (\SqlSemantics\Ast\Tree::outer($node, ['expr_list', 'exprlist', 'nexprlist', 'row_value', 'row_value_explicit', 'no_braces']) as $row) {
                $rows[] = \SqlSemantics\Ast\Tree::outer($row, ['a_expr', 'expr_or_default', 'expr']);
            }
        }
        return $rows;
    }
}

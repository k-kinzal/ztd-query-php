<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The rows an EXPLAIN report returns.
 *
 * Rule: SQLITE-EXPLAIN-ROWS-001. EXPLAIN returns one row per virtual machine
 * instruction with the columns addr, opcode, p1, p2, p3, p4, p5 and comment.
 * EXPLAIN QUERY PLAN returns one row per plan node with the columns id,
 * parent, notused and detail. The model guarantees the number, order, names
 * and storage classes of these columns for the shipped release; the manual
 * reserves the right to change the format between releases and says nothing
 * about the number or content of the rows, which are not modeled. The p4
 * operand and the comment may be NULL (the comment is NULL unless the library
 * is built with SQLITE_ENABLE_EXPLAIN_COMMENTS); every other column is never
 * NULL. Precision: every field is a known type. No diagnostic.
 * Source: https://sqlite.org/lang_explain.html, https://sqlite.org/opcode.html,
 * https://sqlite.org/eqp.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExplainRows
{
    /**
     * The columns of each report: name, storage class, and whether the value can be NULL.
     */
    private const COLUMNS = [
        'Program' => [
            ['addr', Storage::Integer, false],
            ['opcode', Storage::Text, false],
            ['p1', Storage::Integer, false],
            ['p2', Storage::Integer, false],
            ['p3', Storage::Integer, false],
            ['p4', Storage::Text, true],
            ['p5', Storage::Integer, false],
            ['comment', Storage::Text, true],
        ],
        'QueryPlan' => [
            ['id', Storage::Integer, false],
            ['parent', Storage::Integer, false],
            ['notused', Storage::Integer, false],
            ['detail', Storage::Text, false],
        ],
    ];

    /**
     * Answers the output of a report.
     */
    public function fact(ExplainMode $mode, Comparison $names): QueryFact
    {
        $fields = [];
        foreach (self::COLUMNS[$mode->name] as $position => [$name, $storage, $nullable]) {
            $fields[] = new Field($position, new OutputSlot(new Name($name), new Known($storage), $nullable ? Nullability::Nullable : Nullability::NotNull));
        }

        return new QueryFact($fields, $names);
    }
}

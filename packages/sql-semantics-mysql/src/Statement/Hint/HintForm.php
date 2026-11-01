<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint;

/**
 * The form of the arguments of an optimizer hint.
 *
 * Table: an optional query block and a list of tables, each in its own block
 * when no block leads (BKA, MERGE, ...). JoinOrder: the same, for the join
 * order hints. FixedOrder: an optional query block only. Key: an optional
 * query block, one table and a list of its indexes. Semijoin and Subquery:
 * an optional query block and strategies. ExecutionTime: milliseconds.
 * ResourceGroup: a group name. Variable: a variable and a value. BlockName:
 * the name of the query block.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility public
 * @example Reading the form of a hint
 *     \SqlSemantics\Platform\MySql\Statement\Hint\HintName::JoinPrefix->form() // => \SqlSemantics\Platform\MySql\Statement\Hint\HintForm::JoinOrder
 */
enum HintForm
{
    case Table;
    case JoinOrder;
    case FixedOrder;
    case Key;
    case Semijoin;
    case Subquery;
    case ExecutionTime;
    case ResourceGroup;
    case Variable;
    case BlockName;
}

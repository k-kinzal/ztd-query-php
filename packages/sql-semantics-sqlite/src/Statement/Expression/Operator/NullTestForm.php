<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The postfix spellings of a NULL test.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility public
 * @example Reading the spelling of a NULL test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a NOT NULL FROM t');
 *     $query->statement->columns[0]->expression->form // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm::NotNullWords
 */
enum NullTestForm
{
    case IsNull;
    case NotNull;
    case NotNullWords;
}

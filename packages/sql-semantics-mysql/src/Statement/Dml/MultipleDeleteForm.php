<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

/**
 * The two spellings of a multiple-table DELETE: the tables to delete from before FROM, or after FROM with the references after USING.
 *
 * Both request the same deletion; the spelling is kept so the statement is
 * written back as it was given.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility public
 * @example Reading the spelling of a DELETE ... USING
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE FROM t USING t JOIN u ON t.a = u.a');
 *     $delete->statement->form // => \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::Using
 */
enum MultipleDeleteForm
{
    case BeforeFrom;
    case Using;
}

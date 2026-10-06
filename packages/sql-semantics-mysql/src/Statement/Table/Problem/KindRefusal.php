<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

/**
 * Why the server refuses a statement for the kind of relation a name denotes; each case holds the problem in the words of the server.
 *
 * NotView and NotBaseTable are ER_WRONG_OBJECT (1347); a view that DROP
 * TABLE names is ER_BAD_TABLE_ERROR (1051), and one that TRUNCATE TABLE
 * names is ER_NO_SUCH_TABLE (1146), since these statements find base tables
 * only. Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading the message of a refusal
 *     \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView->value // => 'is not VIEW'
 */
enum KindRefusal: string
{
    case NotView = 'is not VIEW';
    case NotBaseTable = 'is not BASE TABLE';
    case UnknownTable = 'is a view: DROP TABLE reports it as an unknown table';
    case NoSuchTable = "is a view: TRUNCATE TABLE reports that the table doesn't exist";
}

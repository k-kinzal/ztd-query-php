<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

/**
 * Which option of the old COPY syntax a string sets: DELIMITER, NULL, QUOTE, ESCAPE or ENCODING.
 *
 * The value is the keyword; each sets the generic option of the same name in lower case.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility public
 * @example Reading the kind of a string option
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COPY t FROM STDIN DELIMITER AS ';'");
 *     $copy->statement->legacy[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTextKind::Delimiter
 */
enum CopyTextKind: string
{
    case Delimiter = 'DELIMITER';
    case Null = 'NULL';
    case Quote = 'QUOTE';
    case Escape = 'ESCAPE';
    case Encoding = 'ENCODING';
}

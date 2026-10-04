<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

/**
 * The keyword ALTER ... RENAME names a role with: ROLE, USER or GROUP.
 *
 * The three spellings rename the same kind of object; the word is kept so
 * that the statement is written back as it was.
 * Source: https://www.postgresql.org/docs/17/sql-alterrole.html, https://www.postgresql.org/docs/17/sql-altergroup.html.
 *
 * @visibility public
 * @example Spelling the group word
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Group->value // => 'GROUP'
 */
enum RoleWord: string
{
    case Role = 'ROLE';
    case User = 'USER';
    case Group = 'GROUP';
}

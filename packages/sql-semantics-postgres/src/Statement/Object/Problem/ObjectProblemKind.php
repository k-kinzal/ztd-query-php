<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Problem;

/**
 * A generic object command that the server rejects.
 *
 * Each case carries the server's message, with `%s` for the subject.
 * Source: https://www.postgresql.org/docs/17/sql-comment.html, https://www.postgresql.org/docs/17/sql-dropindex.html,
 * https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/sql-alteropfamily.html,
 * `get_object_address` in `src/backend/catalog/objectaddress.c` and
 * `RemoveRelations` in `src/backend/commands/tablecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of an unqualified column
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind::UnqualifiedColumn->value // => 'column name must be qualified'
 */
enum ObjectProblemKind: string
{
    case UnqualifiedColumn = 'column name must be qualified';
    case ImproperRelation = 'improper relation name (too many dotted names): %s';
    case ConcurrentMultiple = 'DROP INDEX CONCURRENTLY does not support dropping multiple objects';
    case ConcurrentCascade = 'DROP INDEX CONCURRENTLY does not support CASCADE';
    case ColumnExists = 'column %s already exists';
    case StorageInFamily = 'STORAGE cannot be specified in ALTER OPERATOR FAMILY';
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind;

/**
 * The kinds of object default privileges are set for; the value of a case is its keyword.
 *
 * FUNCTIONS and ROUTINES both set the default privileges of functions and
 * procedures; each spelling is kept as written.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility public
 * @example Reading the kind of object ROUTINES stands for
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind::Routines->object() // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Function
 */
enum DefaultObjectKind: string
{
    case Tables = 'TABLES';
    case Functions = 'FUNCTIONS';
    case Routines = 'ROUTINES';
    case Sequences = 'SEQUENCES';
    case Types = 'TYPES';
    case Schemas = 'SCHEMAS';

    /**
     * Answers the kind of object whose privileges are checked.
     */
    public function object(): PrivilegeObjectKind
    {
        return match ($this) {
            self::Tables => PrivilegeObjectKind::Relation,
            self::Functions, self::Routines => PrivilegeObjectKind::Function,
            self::Sequences => PrivilegeObjectKind::Sequence,
            self::Types => PrivilegeObjectKind::Type,
            self::Schemas => PrivilegeObjectKind::Schema,
        };
    }
}

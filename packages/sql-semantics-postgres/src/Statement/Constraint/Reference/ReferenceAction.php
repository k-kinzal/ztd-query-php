<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

/**
 * What a foreign key does when a referenced row changes.
 *
 * Mirrors the `FKCONSTR_ACTION_*` codes. NO ACTION is the default and is kept
 * when written.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility public
 * @example Reading a referential action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int REFERENCES u ON UPDATE SET NULL)');
 *     $create->statement->definition->elements[0]->qualifiers[0]->actions[0]->action // => \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::SetNull
 */
enum ReferenceAction: string
{
    case NoAction = 'NO ACTION';
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';

    /**
     * Tells whether the action may name the columns it sets.
     */
    public function setsColumns(): bool
    {
        return match ($this) {
            self::SetNull, self::SetDefault => true,
            self::NoAction, self::Restrict, self::Cascade => false,
        };
    }
}

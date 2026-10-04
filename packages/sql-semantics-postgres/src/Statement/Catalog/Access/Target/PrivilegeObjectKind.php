<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

/**
 * The kinds of object privileges are granted on; the value of a case is its keywords.
 *
 * Mirrors the `ObjectType` of `GrantStmt`. A relation is written with the
 * optional word TABLE and covers tables, views, materialized views and
 * foreign tables; for compatibility it also accepts a sequence.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading how the server names a kind in its messages
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::ForeignDataWrapper->subject() // => 'foreign-data wrapper'
 */
enum PrivilegeObjectKind: string
{
    case Relation = 'TABLE';
    case Sequence = 'SEQUENCE';
    case Database = 'DATABASE';
    case Domain = 'DOMAIN';
    case Function = 'FUNCTION';
    case Procedure = 'PROCEDURE';
    case Routine = 'ROUTINE';
    case Language = 'LANGUAGE';
    case LargeObject = 'LARGE OBJECT';
    case Parameter = 'PARAMETER';
    case Schema = 'SCHEMA';
    case Tablespace = 'TABLESPACE';
    case Type = 'TYPE';
    case ForeignDataWrapper = 'FOREIGN DATA WRAPPER';
    case ForeignServer = 'FOREIGN SERVER';

    /**
     * Answers the words the server names the kind with in a message.
     */
    public function subject(): string
    {
        if ($this === self::Relation) {
            return 'relation';
        }

        return $this === self::ForeignDataWrapper ? 'foreign-data wrapper' : strtolower($this->value);
    }
}

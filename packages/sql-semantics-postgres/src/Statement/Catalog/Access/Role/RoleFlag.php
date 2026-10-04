<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

/**
 * The role attributes written as a plain word, such as SUPERUSER or NOLOGIN.
 *
 * The grammar reads these words as identifiers and compares them with this
 * fixed list; the value of a case is the word as the server compares it.
 * INHERIT is not in the list: it is a keyword with a production of its own.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the option a word fills and whether it turns the attribute on
 *     $flag = \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::NoLogin;
 *     [$flag->option(), $flag->enabled()] // => ['canlogin', false]
 */
enum RoleFlag: string
{
    case Superuser = 'superuser';
    case NoSuperuser = 'nosuperuser';
    case CreateRole = 'createrole';
    case NoCreateRole = 'nocreaterole';
    case Replication = 'replication';
    case NoReplication = 'noreplication';
    case CreateDb = 'createdb';
    case NoCreateDb = 'nocreatedb';
    case Login = 'login';
    case NoLogin = 'nologin';
    case BypassRls = 'bypassrls';
    case NoBypassRls = 'nobypassrls';
    case NoInherit = 'noinherit';

    /**
     * Answers the option of the server the word fills.
     */
    public function option(): string
    {
        return match ($this) {
            self::Superuser, self::NoSuperuser => 'superuser',
            self::CreateRole, self::NoCreateRole => 'createrole',
            self::Replication, self::NoReplication => 'isreplication',
            self::CreateDb, self::NoCreateDb => 'createdb',
            self::Login, self::NoLogin => 'canlogin',
            self::BypassRls, self::NoBypassRls => 'bypassrls',
            self::NoInherit => 'inherit',
        };
    }

    /**
     * Tells whether the word turns the attribute on.
     */
    public function enabled(): bool
    {
        return !str_starts_with($this->value, 'no');
    }
}

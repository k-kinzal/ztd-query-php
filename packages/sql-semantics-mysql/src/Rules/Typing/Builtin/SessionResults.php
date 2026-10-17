<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves the results of information functions: names of the session as utf8mb3 strings.
 *
 * The server keeps its metadata in utf8mb3: the names of the session, the user and the version
 * hold like system constants, and the character set and collation names CHARSET() and
 * COLLATION() answer are coercible, all in utf8mb3_general_ci. The list of active roles
 * CURRENT_ROLE() answers is a LONGTEXT of 50331648 characters (verified on a live 8.4 server).
 * MySQL 5.6 and 5.7 answer a database name of 34 characters and an account of 77 and 93
 * characters, as their user names are shorter (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-metadata.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class SessionResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $name = static fn (int $length, Coercibility $coercibility = Coercibility::SystemConstant): Closure => static fn (Invocation $call): Domain => Domain::string($length, Collation::known('utf8mb3_general_ci'), Field::VarString, $coercibility);
        $released = static fn (int $modern, int $s56, int $s57): Closure => static fn (Invocation $call): Domain => $name(match ($call->derivation->context->profile->grammar) {
            GrammarRelease::MySql5651 => $s56,
            GrammarRelease::MySql5744 => $s57,
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => $modern,
        })($call);

        return [
            'DATABASE' => $released(64, 34, 34),
            'SCHEMA' => $released(64, 34, 34),
            'USER' => $released(288, 77, 93),
            'SESSION_USER' => $released(288, 77, 93),
            'SYSTEM_USER' => $released(288, 77, 93),
            'CURRENT_USER' => $released(288, 77, 93),
            'CURRENT_ROLE' => static fn (Invocation $call): Domain => Domain::string(50331648, Collation::known('utf8mb3_general_ci'), Field::LongBlob, Coercibility::SystemConstant),
            'CHARSET' => $name(64, Coercibility::Coercible),
            'COLLATION' => $name(64, Coercibility::Coercible),
            'VERSION' => static fn (Invocation $call): Domain => $name(strlen(substr($call->derivation->context->profile->grammar->value, strlen('mysql-'))))($call),
        ];
    }
}

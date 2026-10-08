<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves the results of information functions: names of the session as utf8mb3 strings.
 *
 * The server keeps its metadata in utf8mb3: the names of the session, the user and the version
 * hold like system constants, and the character set and collation names CHARSET() and
 * COLLATION() answer are coercible, all in utf8mb3_general_ci.
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

        return [
            'DATABASE' => $name(64),
            'SCHEMA' => $name(64),
            'USER' => $name(288),
            'SESSION_USER' => $name(288),
            'SYSTEM_USER' => $name(288),
            'CURRENT_USER' => $name(288),
            'CHARSET' => $name(64, Coercibility::Coercible),
            'COLLATION' => $name(64, Coercibility::Coercible),
            'VERSION' => static fn (Invocation $call): Domain => $name(strlen(substr($call->derivation->context->profile->grammar->value, strlen('mysql-'))))($call),
        ];
    }
}

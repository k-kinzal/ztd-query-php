<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;
use stdClass;

/**
 * Derives the attribute diagnostic of CREATE USER and ALTER USER.
 *
 * Rule: MYSQL-ACCOUNT-CHECKS-001. A REQUIRE clause that names a property
 * twice and numeric options outside their domain are refused before ordinary
 * construction. The typed values enforce these intrinsic constraints.
 * An ATTRIBUTE string that is not a JSON object is InvalidUserAttribute;
 * the JSON text is read with the JSON grammar of RFC 8259, which the server
 * also implements. The clause holds no expressions and contributes no type
 * facts. Terminates: one bounded JSON decode. Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountChecks
{
    /**
     * Reports an ATTRIBUTE string that is not a JSON object.
     */
    public function comment(Derivation $derivation, ?UserComment $comment): void
    {
        if ($comment?->kind === CommentKind::Attribute && !json_decode($comment->text->value, false, 512, JSON_BIGINT_AS_STRING) instanceof stdClass) {
            $derivation->report(new InvalidUserAttribute($comment->text->value));
        }
    }

}

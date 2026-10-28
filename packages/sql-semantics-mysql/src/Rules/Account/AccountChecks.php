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
 * twice is refused before construction. Numeric options retain their values;
 * their integer-token and range conditions are derived here.
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

    /**
     * Publishes numeric option conditions without losing later options or accounts.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption> $options The written options
     */
    public function options(Derivation $derivation, array $options): void
    {
        foreach ($options as $option) {
            if ($option->number === null || !(new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integer($derivation, $option->number)) {
                continue;
            }
            $problem = (new NumberChecks())->option($option->kind, $option->number);
            if ($problem !== null) {
                $derivation->report($problem);
                $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $option);
            }
        }
    }

}

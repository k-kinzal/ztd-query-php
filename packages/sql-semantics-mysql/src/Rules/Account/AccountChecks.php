<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use stdClass;

/**
 * Derives the diagnostics of the account clauses of CREATE USER, ALTER USER and GRANT.
 *
 * Rule: MYSQL-ACCOUNT-CHECKS-001. A REQUIRE clause that names a property
 * twice is RepeatedTlsAttribute (ER_DUP_ARGUMENT). PASSWORD EXPIRE INTERVAL
 * takes 1 to 65535 days, FAILED_LOGIN_ATTEMPTS and PASSWORD_LOCK_TIME 0 to
 * 32767, every other number of an option any integer (MYSQL-ACCOUNT-NUMBER-001).
 * An ATTRIBUTE string that is not a JSON object is InvalidUserAttribute;
 * the JSON text is read with the JSON grammar of RFC 8259, which the server
 * also implements. A factor change names factors 2 or 3, two changes name
 * different factors, and two ADDs add 2 before 3 (InvalidFactorPair). No
 * type facts: the clauses hold no expressions. Terminates: one pass over
 * each list. Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountChecks
{
    /**
     * Reports the problems of a REQUIRE clause, when one is written.
     */
    public function tls(Derivation $derivation, ?TlsRequirement $tls): void
    {
        $seen = [];
        foreach ($tls === null ? [] : $tls->conditions as $condition) {
            if (isset($seen[$condition->attribute->value])) {
                $derivation->report(new RepeatedTlsAttribute($condition->attribute));
            }
            $seen[$condition->attribute->value] = true;
        }
    }

    /**
     * Reports the numbers of password and locking options outside their range.
     *
     * @param list<AccountOption> $options
     */
    public function options(Derivation $derivation, array $options): void
    {
        $numbers = new NumberChecks();
        $ranges = [
            AccountOptionKind::ExpireInterval->name => ['DAY', '1', '65535'],
            AccountOptionKind::FailedLoginAttempts->name => ['FAILED_LOGIN_ATTEMPTS', '0', '32767'],
            AccountOptionKind::LockTime->name => ['PASSWORD_LOCK_TIME', '0', '32767'],
        ];
        foreach ($options as $option) {
            if ($option->number !== null) {
                [$name, $minimum, $maximum] = $ranges[$option->kind->name] ?? [implode(' ', $option->kind->words()), '0', null];
                $numbers->range($derivation, $option->number, $name, $minimum, $maximum);
            }
        }
    }

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
     * Reports the factor numbers and the factor pair of an ALTER USER factor change.
     */
    public function factors(Derivation $derivation, FactorChange $change): void
    {
        $numbers = new NumberChecks();
        foreach ($change->steps as $step) {
            $numbers->factor($derivation, $step->factor);
        }
        if (count($change->steps) === 2) {
            $first = $change->steps[0]->factor->text;
            $second = $change->steps[1]->factor->text;
            if ($first === $second) {
                $derivation->report(new InvalidFactorPair(true));
            } elseif ($change->action === FactorAction::Add && $numbers->compare($first, $second) > 0) {
                $derivation->report(new InvalidFactorPair(false));
            }
        }
    }
}

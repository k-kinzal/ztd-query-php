<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Attributes;
use MySqlMemory\Account\Credentials;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Operation;

/**
 * Applies the authentication and the options of CREATE USER and ALTER USER to an account.
 *
 * A number out of range and a TLS property named twice are refused while the statement is
 * parsed (ER_WRONG_VALUE, ER_ONLY_INTEGERS_ALLOWED, ER_DUP_ARGUMENT). An authentication names a
 * plugin, which must be loaded, or keeps the plugin of the account; a password is stored as the
 * plugin stores it and clears an expired password, a random password is generated and answered,
 * an authentication string is taken as it is, and a plugin named without a credential empties
 * the password and, in ALTER USER, expires it (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html.
 *
 * @visibility MySqlMemory
 */
final class Options
{
    /**
     * Raises the first problem the server finds while it parses the options of a statement, quoting the text of the statement from a number it refuses.
     *
     * @throws SqlError When an option is refused
     */
    public function parsed(Operation $operation, string $text): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof NumberOutOfRange) {
                $at = strpos($text, $diagnostic->number);
                throw $diagnostic->error === 'ER_WRONG_VALUE' ? ErrorCode::WrongValue->error($diagnostic->option, $diagnostic->number) : new SqlError(ErrorCode::ParseError, "Only integers allowed as number here near '" . mb_strcut($at === false ? '' : substr($text, $at), 0, 80, 'UTF-8') . "' at line 1");
            }
            if ($diagnostic instanceof RepeatedTlsAttribute) {
                throw ErrorCode::DuplicateArgument->error($diagnostic->attribute->value);
            }
            if ($diagnostic instanceof InvalidFactorPair) {
                throw $diagnostic->identical ? ErrorCode::FactorIdentical->error() : ErrorCode::FactorOrder->error(2, 3);
            }
        }
    }

    /**
     * Checks the plugin and the authentication string of an authentication, before any account is looked up.
     *
     * @throws SqlError When the plugin is not loaded or the string has not its form
     */
    public function check(?Identification $identification, string $plugin): void
    {
        if ($identification === null) {
            return;
        }
        $credentials = new Credentials();
        $name = $identification->plugin === null ? $plugin : $credentials->plugin($identification->plugin->value);
        if ($identification->credential === Credential::Hash || $identification->credential === Credential::PasswordHash) {
            $credentials->check($name, $identification->secret->value ?? '');
        }
    }

    /**
     * Applies an authentication to an account and answers the random password it generated, if any.
     *
     * @param bool $altered Whether ALTER USER changes the account, where a plugin alone expires the password
     *
     * @throws SqlError When the plugin is not loaded or the string has not its form
     */
    public function identify(Account $account, Identification $identification, bool $altered): ?string
    {
        $credentials = new Credentials();
        $account->plugin = $identification->plugin === null ? $account->plugin : $credentials->plugin($identification->plugin->value);
        $secret = $identification->secret->value ?? '';
        $generated = null;
        switch ($identification->credential) {
            case Credential::None:
                $account->hash = '';
                $account->password = '';
                $account->expired = $altered;

                return null;
            case Credential::Hash:
            case Credential::PasswordHash:
                $credentials->check($account->plugin, $secret);
                $account->hash = $secret;
                $account->password = $secret === '' ? '' : null;
                $account->expired = false;

                return null;
            case Credential::RandomPassword:
                $generated = $credentials->generate();
                $secret = $generated;
                break;
            case Credential::Password:
                break;
        }
        $account->hash = $credentials->hash($account->plugin, $secret);
        $account->password = $secret;
        $account->expired = false;

        return $generated;
    }

    /**
     * Applies the TLS requirement, the resource limits, the password and lock options and the comment or attributes of a statement to an account.
     *
     * @param list<ResourceLimit> $resources
     * @param list<AccountOption> $options
     *
     * @throws SqlError When the attributes are not a JSON object
     */
    public function apply(Account $account, ?TlsRequirement $tls, array $resources, array $options, ?UserComment $comment): void
    {
        if ($tls !== null) {
            $account->tls = $tls->kind;
            $conditions = [];
            foreach ($tls->conditions as $condition) {
                $conditions[$condition->attribute->value] = $condition->value->value;
            }
            $account->tlsConditions = $tls->kind === TlsKind::Specified ? array_filter(['SUBJECT' => $conditions['SUBJECT'] ?? null, 'ISSUER' => $conditions['ISSUER'] ?? null, 'CIPHER' => $conditions['CIPHER'] ?? null], static fn (?string $value): bool => $value !== null) : [];
        }
        foreach ($resources as $resource) {
            $account->limits[$resource->kind->value] = $this->number($resource->number);
        }
        foreach ($options as $option) {
            $this->option($account, $option);
        }
        if ($comment !== null) {
            $attributes = new Attributes();
            $account->attributes = $comment->kind === CommentKind::Comment ? $attributes->comment($account->attributes, $comment->text->value) : $attributes->merge($account->attributes, $comment->text->value);
        }
    }

    /**
     * Applies one password or lock option to an account.
     */
    public function option(Account $account, AccountOption $option): void
    {
        $number = $option->number === null ? 0 : $this->number($option->number);
        match ($option->kind) {
            AccountOptionKind::AccountUnlock => $account->locked = false,
            AccountOptionKind::AccountLock => $account->locked = true,
            AccountOptionKind::ExpireNow => $account->expired = true,
            AccountOptionKind::ExpireInterval => $account->lifetime = $number,
            AccountOptionKind::ExpireNever => $account->lifetime = 0,
            AccountOptionKind::ExpireDefault => $account->lifetime = null,
            AccountOptionKind::HistoryCount => $account->history = $number,
            AccountOptionKind::HistoryDefault => $account->history = null,
            AccountOptionKind::ReuseInterval => $account->reuse = $number,
            AccountOptionKind::ReuseDefault => $account->reuse = null,
            AccountOptionKind::RequireCurrent => $account->requireCurrent = true,
            AccountOptionKind::RequireCurrentDefault => $account->requireCurrent = null,
            AccountOptionKind::RequireCurrentOptional => $account->requireCurrent = false,
            AccountOptionKind::FailedLoginAttempts => $account->failedAttempts = $number,
            AccountOptionKind::LockTime => $account->lockTime = $number,
            AccountOptionKind::LockTimeUnbounded => $account->lockTime = -1,
        };
    }

    /**
     * Answers the value of a number an option takes: the number in 64 bits, saturated, of which the server keeps the low 32 bits (verified on a live 8.4 server).
     */
    public function number(Numeral $number): int
    {
        $value = (new NumberChecks())->value($number) ?? '0';
        if (strlen($value) > 20 || (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0)) {
            $value = '18446744073709551615';
        }
        $kept = 0;
        foreach (str_split($value) as $digit) {
            $kept = ($kept * 10 + (int) $digit) % 4294967296;
        }

        return $kept;
    }
}

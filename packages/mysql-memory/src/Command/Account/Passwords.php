<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Answers the random passwords CREATE USER, ALTER USER and SET PASSWORD generate.
 *
 * Each row holds the user name, the host, the generated password and the authentication factor
 * it is for; the columns are text of 4, 4 and 18 characters and an unsigned BIGINT of 13 digits
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/password-management.html#random-password-generation.
 *
 * @visibility MySqlMemory
 */
final class Passwords
{
    /**
     * Answers the result set of generated passwords.
     *
     * @param list<array{Identity, string}> $generated The account and the password generated for it
     */
    public function result(array $generated, Context $context): ResultSet
    {
        $text = static fn (string $name, int $length): ResultColumn => new ResultColumn($name, Field::VarString, $length, 31, ColumnFlag::NotNull->value, 255);
        $columns = [
            $text('user', 16),
            $text('host', 16),
            $text('generated password', 72),
            new ResultColumn('auth_factor', Field::LongLong, 13, 0, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value, 63),
        ];
        $rows = array_map(static fn (array $row): array => [$row[0]->user, $row[0]->host, $row[1], '1'], $generated);

        return new ResultSet($columns, $rows, $context->diagnostics->count());
    }
}

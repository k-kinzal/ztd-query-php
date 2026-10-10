<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Passwords;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Passwords::class)]
#[Small]
final class PasswordsTest extends TestCase
{
    public function testResultAnswersARowForEachPassword(): void
    {
        $session = (new Instance())->connect();

        $result = (new Passwords())->result([[new Identity('u', '%'), 'pw']], new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([[['u', '%', 'pw', '1']], [255, 255, 255, 63], 32929], [$result->rows, array_map(static fn ($column): int => $column->charset, $result->columns), $result->columns[3]->flags]);
    }
}

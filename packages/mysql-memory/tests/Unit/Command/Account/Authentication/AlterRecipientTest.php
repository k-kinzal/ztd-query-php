<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Authentication\AlterRecipient;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlterRecipient::class)]
#[Small]
final class AlterRecipientTest extends TestCase
{
    public function testApplyKeepsLegacyChangesAndAppendsTheAggregateError(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run("ALTER USER absent IDENTIFIED WITH absent_plugin BY 'x', CURRENT_USER IDENTIFIED BY 'new'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1524, $answers[0]->getCode());
        self::assertSame([1524, 1396], array_column($session->diagnostics->conditions, 1));
        self::assertSame('new', $session->instance->accounts->find(new Identity('root', '%'))?->password);
    }
}

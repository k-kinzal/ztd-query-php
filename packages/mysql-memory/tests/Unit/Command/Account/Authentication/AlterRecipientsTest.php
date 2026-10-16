<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Authentication\AlterRecipients;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlterRecipients::class)]
#[Small]
final class AlterRecipientsTest extends TestCase
{
    public function testApplyKeepsEveryRecipientConditionInOrder(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("ALTER USER CURRENT_USER IDENTIFIED BY 'new' REPLACE 'wrong', absent IDENTIFIED WITH absent_plugin BY 'x'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(3891, $answers[0]->getCode());
        self::assertSame([3891, 1524], array_column($session->diagnostics->conditions, 1));
        self::assertFalse($session->passwordExpired);
    }

    public function testApplyRollsBackCredentialsButRequiresResetAfterASuccessfulSelfOccurrence(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET PASSWORD='old'");
        $answers = $session->run("ALTER USER CURRENT_USER IDENTIFIED BY 'changed', CURRENT_USER IDENTIFIED BY 'last' REPLACE 'old'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(3891, $answers[0]->getCode());
        self::assertSame('old', $session->instance->accounts->find(new Identity('root', '%'))?->password);
        self::assertTrue($session->passwordExpired);
        $session->query("SET PASSWORD='new'");
        self::assertFalse($session->passwordExpired);
    }

}

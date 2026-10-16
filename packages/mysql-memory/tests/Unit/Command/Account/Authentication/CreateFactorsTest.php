<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Command\Account\Authentication\CreateFactors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateFactors::class)]
#[Small]
final class CreateFactorsTest extends TestCase
{
    public function testCheckReportsOnlyTheFirstOmittedFactorBeforeExistence(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER existing');
        $answers = $session->run("CREATE USER existing AND IDENTIFIED BY 'x' AND IDENTIFIED BY 'y'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1396, $answers[0]->getCode());
        self::assertSame([4058, 1396], array_column($session->diagnostics->conditions, 1));
        self::assertStringStartsWith('2 factor', $session->diagnostics->conditions[0][2]);
    }

    public function testCheckValidatesNewRecipientsBeforeReportingOtherExistingRecipients(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("CREATE USER CURRENT_USER, fresh AND IDENTIFIED WITH caching_sha2_password AND IDENTIFIED BY 'x'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(4052, $answers[0]->getCode());
        self::assertSame([4058, 4052], array_column($session->diagnostics->conditions, 1));
        self::assertStringStartsWith('3 factor', $session->diagnostics->conditions[0][2]);
    }
}

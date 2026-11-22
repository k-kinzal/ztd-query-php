<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\AccountChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AccountChecks::class)]
#[Medium]
final class AccountChecksTest extends TestCase
{
    public function testTlsReportsARepeatedProperty(): void
    {
        self::assertInstanceOf(RepeatedTlsAttribute::class, (new Inspection())->diagnose("CREATE USER u REQUIRE CIPHER 'a' AND CIPHER 'b'")->facts->diagnostics[0]);
    }

    public function testOptionsReportsNumbersOutsideTheirRange(): void
    {
        self::assertCount(2, (new Inspection())->diagnose('CREATE USER u FAILED_LOGIN_ATTEMPTS 32768 PASSWORD_LOCK_TIME 1.5')->facts->diagnostics);
    }

    public function testCommentAcceptsOnlyAJsonObject(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(InvalidUserAttribute::class, $semantics->diagnose("ALTER USER u ATTRIBUTE '1'")->facts->diagnostics[0]);
        self::assertSame([], $semantics->diagnose("ALTER USER u ATTRIBUTE '{\"a\": 1}'")->facts->diagnostics);
    }

    public function testFactorsReportsTheSameFactorTwice(): void
    {
        self::assertInstanceOf(InvalidFactorPair::class, (new Inspection())->diagnose('ALTER USER u DROP 3 FACTOR DROP 3 FACTOR')->facts->diagnostics[0]);
    }
}

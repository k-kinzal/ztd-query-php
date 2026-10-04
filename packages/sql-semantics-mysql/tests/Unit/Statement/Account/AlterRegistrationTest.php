<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;

#[CoversClass(AlterRegistration::class)]
#[Medium]
final class AlterRegistrationTest extends TestCase
{
    public function testDeriveStatementReportsAnInvalidFactor(): void
    {
        self::assertInstanceOf(NumberOutOfRange::class, (new Semantics(Dialect::MySql))->analyze('ALTER USER u 4 FACTOR UNREGISTER')->facts->diagnostics[0]);
    }

    public function testRenderWritesTheStep(): void
    {
        self::assertSame("ALTER USER u 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'", (new Semantics(Dialect::MySql))->analyze("alter user u 2 factor finish registration set challenge_response as 'r'")->toString());
    }
}

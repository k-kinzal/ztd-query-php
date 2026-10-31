<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\KillUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(KillUse::class)]
#[Medium]
final class KillUseTest extends TestCase
{
    public function testErrorReportsTheWidthFailureAndFollowingProcessCondition(): void
    {
        $error = (new KillUse())->error(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), (new Instance())->connect());
        self::assertSame(1241, $error->getCode());
        self::assertSame([[1204, 'You may only use constant expressions with SET']], $error->following);
    }

    public function testRaiseCountsTheReachedInstructionBeforeReportingItsCondition(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new KillUse())->raise(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), $session);
    }
}

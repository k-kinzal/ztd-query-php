<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\PurgeUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(PurgeUse::class)]
#[Medium]
final class PurgeUseTest extends TestCase
{
    public function testErrorReportsTheWidthFailureAndFollowingCutoffCondition(): void
    {
        $error = (new PurgeUse())->error(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), (new Instance())->connect());
        self::assertSame(1241, $error->getCode());
        self::assertSame([[1210, 'Incorrect arguments to PURGE LOGS BEFORE']], $error->following);
    }

    public function testRaiseCountsTheReachedInstructionBeforeReportingItsCondition(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new PurgeUse())->raise(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), $session);
    }

    public function testUnboundFindsTheDefaultDatabaseBeforePreparingAnyCall(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('f'));
        $row = new Row([new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('f'), [], new \SqlSemantics\Statement\Identifier\Name('d')), $call]);
        $session = (new Instance())->connect();

        self::assertSame(1046, (new PurgeUse())->unbound($row, $session)?->getCode());
        self::assertNull((new PurgeUse())->unbound(new NumberLiteral('1'), $session));
        $session->query('CREATE DATABASE d; USE d');
        self::assertNull((new PurgeUse())->unbound($row, $session));
    }
}

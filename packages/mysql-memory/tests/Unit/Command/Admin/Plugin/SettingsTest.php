<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Plugin;

use MySqlMemory\Command\Admin\Plugin\Settings;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Settings::class)]
#[Small]
final class SettingsTest extends TestCase
{
    public function testPreparePreservesEarlierSideEffectsAfterALaterValueFailure(): void
    {
        $session = (new Instance())->connect();
        $session->run("INSTALL COMPONENT 'no_scheme' SET GLOBAL x=(@component_value:=3), y=VALUES(absent)");
        self::assertSame([1287, 1054], array_column($session->diagnostics->conditions, 1));
        $result = $session->query('SELECT @component_value')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
    }

    public function testPrepareNamesTheQualifiedVariableForANullValue(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(4160);
        $this->expectExceptionMessage('The value supplied for c.x in the SET list cannot be null');
        $session->query("INSTALL COMPONENT 'no_scheme' SET GLOBAL c.x=NULL");
    }
    public function testPrepareEvaluatesANonNullValueAgainBeforeComponentLoading(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @side=0');
        $session->run("INSTALL COMPONENT 'noscheme' SET GLOBAL x=(@side:=@side+1), y=0+'x'");
        self::assertSame([1287, 1292, 1292, 3527], array_column($session->diagnostics->conditions, 1));
        $result = $session->query('SELECT @side')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testPrepareKeepsTheRuntimeErrorBeforeItsNullSettingCondition(): void
    {
        $session = (new Instance())->connect();
        $session->run("INSTALL COMPONENT 'noscheme' SET GLOBAL x=JSON_EXTRACT('bad','$')");
        self::assertSame([3141, 4160], array_column($session->diagnostics->conditions, 1));
    }

}

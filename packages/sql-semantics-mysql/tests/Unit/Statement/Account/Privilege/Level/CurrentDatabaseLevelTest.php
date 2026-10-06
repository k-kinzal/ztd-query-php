<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;

#[CoversClass(CurrentDatabaseLevel::class)]
#[Medium]
final class CurrentDatabaseLevelTest extends TestCase
{
    public function testDescribeNamesTheLevel(): void
    {
        self::assertSame('the current database', (new CurrentDatabaseLevel())->describe());
    }

    public function testRenderWritesStar(): void
    {
        self::assertSame('GRANT SELECT ON * TO u', (new Semantics(Dialect::MySql))->analyze('grant select on * to u')->toString());
    }
}

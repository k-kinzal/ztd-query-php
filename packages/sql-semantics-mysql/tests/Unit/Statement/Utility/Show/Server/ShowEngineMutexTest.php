<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;

#[CoversClass(ShowEngineMutex::class)]
#[Medium]
final class ShowEngineMutexTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW ENGINE innodb MUTEX');
        self::assertInstanceOf(ShowEngineMutex::class, $show->statement);
        self::assertSame('Type', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINE innodb MUTEX', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW ENGINE innodb MUTEX')->toString());
    }
}

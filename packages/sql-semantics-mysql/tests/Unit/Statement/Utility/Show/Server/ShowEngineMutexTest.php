<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowEngineMutex::class)]
#[Medium]
final class ShowEngineMutexTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.7.44'))->inspect('SHOW ENGINE innodb MUTEX');
        self::assertInstanceOf(ShowEngineMutex::class, $show->statement);
        self::assertSame('Type', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINE innodb MUTEX', (new Inspection('mysql-5.7.44'))->inspect('SHOW ENGINE innodb MUTEX')->toString());
    }
}

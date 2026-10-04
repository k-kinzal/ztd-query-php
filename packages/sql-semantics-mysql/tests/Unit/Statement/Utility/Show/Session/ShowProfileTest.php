<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile;

#[CoversClass(ShowProfile::class)]
#[Medium]
final class ShowProfileTest extends TestCase
{
    public function testDeriveStatementAddsTheColumnsOfTheSections(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW PROFILE MEMORY, IPC LIMIT 1 OFFSET 2');
        self::assertInstanceOf(ShowProfile::class, $show->statement);
        self::assertSame(['Status', 'Duration', 'Messages_sent', 'Messages_received'], [$show->field(0)->name?->value, $show->field(1)->name?->value, $show->field(2)->name?->value, $show->field(3)->name?->value]);
        self::assertSame(4, $show->fields()?->count());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PROFILE MEMORY, IPC LIMIT 1 OFFSET 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW PROFILE MEMORY, IPC LIMIT 1 OFFSET 2')->toString());
    }
}

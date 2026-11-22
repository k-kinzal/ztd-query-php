<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowProcesslist::class)]
#[Medium]
final class ShowProcesslistTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection())->inspect('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcesslist::class, $show->statement);
        self::assertSame('Id', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PROCESSLIST', (new Inspection())->inspect('SHOW PROCESSLIST')->toString());
    }
}

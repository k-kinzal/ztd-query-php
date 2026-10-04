<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPlugins;

#[CoversClass(ShowPlugins::class)]
#[Medium]
final class ShowPluginsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW PLUGINS');
        self::assertInstanceOf(ShowPlugins::class, $show->statement);
        self::assertSame('Name', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PLUGINS', (new Semantics(Dialect::MySql))->analyze('SHOW PLUGINS')->toString());
    }
}

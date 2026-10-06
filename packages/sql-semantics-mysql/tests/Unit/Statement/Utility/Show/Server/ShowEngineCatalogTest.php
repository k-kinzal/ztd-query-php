<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;

#[CoversClass(ShowEngineCatalog::class)]
#[Medium]
final class ShowEngineCatalogTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW STORAGE ENGINES');
        self::assertInstanceOf(ShowEngineCatalog::class, $show->statement);
        self::assertSame('Engine', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW STORAGE ENGINES')->toString());
    }
}

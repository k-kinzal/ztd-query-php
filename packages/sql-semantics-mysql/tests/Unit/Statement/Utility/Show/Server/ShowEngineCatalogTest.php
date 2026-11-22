<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowEngineCatalog::class)]
#[Medium]
final class ShowEngineCatalogTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect('SHOW STORAGE ENGINES');
        self::assertInstanceOf(ShowEngineCatalog::class, $show->statement);
        self::assertSame('Engine', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINES', (new Inspection('mysql-5.6.51'))->inspect('SHOW STORAGE ENGINES')->toString());
    }
}

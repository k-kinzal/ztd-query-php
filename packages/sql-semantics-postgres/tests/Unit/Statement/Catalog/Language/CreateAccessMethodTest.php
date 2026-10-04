<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateAccessMethod::class)]
#[Medium]
final class CreateAccessMethodTest extends TestCase
{
    public function testRenderWritesTheKindAndHandler(): void
    {
        self::assertSame('CREATE ACCESS METHOD heap2 TYPE TABLE HANDLER heap_tableam_handler', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ACCESS METHOD heap2 TYPE TABLE HANDLER heap_tableam_handler')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ACCESS METHOD b TYPE INDEX HANDLER s.h')->facts->diagnostics);
    }
}

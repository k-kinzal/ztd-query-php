<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastConversion::class)]
#[Medium]
final class CastConversionTest extends TestCase
{
    public function testRenderWritesInout(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastConversion::InOut->render($out);
        self::assertSame('WITH INOUT', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CAST (a AS b) WITHOUT FUNCTION')->facts->diagnostics);
    }
}

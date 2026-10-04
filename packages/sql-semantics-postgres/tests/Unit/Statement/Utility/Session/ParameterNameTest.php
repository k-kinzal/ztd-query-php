<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName::class)]
#[Medium]
final class ParameterNameTest extends TestCase
{
    public function testTextJoinsThePartsWithDots(): void
    {
        self::assertSame('a.b', (new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('b')]))->text());
    }

    public function testRenderQuotesAKeywordPart(): void
    {
        self::assertSame('SET "session".x TO 1', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET "session".x = 1')->toString());
    }

    public function testRenderKeepsCaseOfAQuotedPart(): void
    {
        self::assertSame('RESET "A".b', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('RESET "A".b')->toString());
    }

    public function testANameNeedsAPart(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('A parameter name has at least one part.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([]);
    }
}

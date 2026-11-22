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
        self::assertSame('a.b', (new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b')]))->text());
    }

    public function testRenderQuotesAKeywordPart(): void
    {
        self::assertSame('SET "select".x TO 1', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SET "select".x = 1')->toString());
    }

    public function testRenderKeepsCaseOfAQuotedPart(): void
    {
        self::assertSame('RESET "A".b', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET "A".b')->toString());
    }
}

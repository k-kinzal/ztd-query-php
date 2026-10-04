<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName::class)]
#[Medium]
final class ParameterNameTest extends TestCase
{
    public function testParameterJoinsTheParts(): void
    {
        self::assertSame('myext.setting', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName([new \SqlSemantics\Statement\Identifier\Name('myext'), new \SqlSemantics\Statement\Identifier\Name('setting')]))->parameter());
    }

    public function testRenderQuotesEveryPartAsAColumnName(): void
    {
        self::assertSame('GRANT set ON PARAMETER a."select".c TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SET ON PARAMETER a."select".c TO joe')->toString());
    }

    public function testRejectsNoPart(): void
    {
        $this->expectExceptionMessage('A parameter name has at least one part.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName([]);
    }
}

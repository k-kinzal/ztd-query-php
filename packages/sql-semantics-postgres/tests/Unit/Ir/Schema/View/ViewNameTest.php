<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;

#[CoversClass(ViewName::class)]
#[Medium]
final class ViewNameTest extends TestCase
{
    public function testQuotedUsesBothAbsoluteComponents(): void
    {
        $name = new ViewName(new Identifier('EU Data'), new Identifier('users"daily'));

        self::assertSame('"EU Data"."users""daily"', $name->quoted());
    }
}

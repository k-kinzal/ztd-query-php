<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName;

#[CoversClass(ViewName::class)]
#[Small]
final class ViewNameTest extends TestCase
{
    public function testQuotedUsesTheAbsoluteName(): void
    {
        $table = new ViewName(new Identifier('app'), new Identifier('a'));

        self::assertSame('`app`.`a`', $table->quoted());
    }
}

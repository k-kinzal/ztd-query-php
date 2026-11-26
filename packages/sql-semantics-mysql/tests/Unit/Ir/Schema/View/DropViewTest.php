<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName;

#[CoversClass(DropView::class)]
#[Small]
final class DropViewTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeRelease(): void
    {
        $drop = new DropView([new ViewName(new Identifier('app'), new Identifier('a'))]);

        self::assertStringStartsWith('mysql-9.1.0/', $drop->profileId());
    }

    public function testToStringRetainsTheOrderedTargets(): void
    {
        $schema = new Identifier('app');
        $drop = new DropView([new ViewName($schema, new Identifier('b')), new ViewName($schema, new Identifier('a'))], true);

        self::assertSame('DROP VIEW IF EXISTS `app`.`b`, `app`.`a`', $drop->toString());
    }
}

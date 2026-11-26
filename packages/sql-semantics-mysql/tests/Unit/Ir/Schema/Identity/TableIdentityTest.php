<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(TableIdentity::class)]
#[Small]
final class TableIdentityTest extends TestCase
{
    public function testQuotedUsesTheAbsoluteTableCoordinates(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('Today'));
        $target = new TableIdentity($name, ExternalRelationKind::TemporaryTable);

        self::assertSame($name, $target->name);
        self::assertSame('`app`.`Today`', $target->quoted());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(IndexIdentity::class)]
#[Small]
final class IndexIdentityTest extends TestCase
{
    public function testKeepsThePhysicalPrimaryKeyRole(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('orders'));
        $owner = new TableIdentity($name, ExternalRelationKind::BaseTable);
        $target = new IndexIdentity($owner, new Identifier('PRIMARY'), IndexRole::Primary);

        self::assertSame('PRIMARY', $target->name->value);
        self::assertSame(IndexRole::Primary, $target->role);
        self::assertSame($owner, $target->owner);
    }
}

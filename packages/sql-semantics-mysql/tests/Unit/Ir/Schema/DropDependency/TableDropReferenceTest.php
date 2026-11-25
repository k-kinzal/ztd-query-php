<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\DropDependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\TableDropReference;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(TableDropReference::class)]
#[Medium]
final class TableDropReferenceTest extends TestCase
{
    public function testRetainsBothPersistentRoles(): void
    {
        $schema = new Identifier('app');
        $child = new TableIdentity(new TableName($schema, new Identifier('child')), ExternalRelationKind::BaseTable);
        $parent = new TableIdentity(new TableName($schema, new Identifier('parent')), ExternalRelationKind::BaseTable);
        $foreign = new TableDropReference($child, $parent);

        self::assertSame($child, $foreign->child);
        self::assertSame($parent, $foreign->parent);
    }
}

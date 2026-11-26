<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(IndexForeignKeyRequirement::class)]
#[Small]
final class IndexForeignKeyRequirementTest extends TestCase
{
    public function testKeysAreDetachedFromInputReferences(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('t')), ExternalRelationKind::BaseTable);
        $name = new Identifier('preferred');
        $requirement = new IndexForeignKeyRequirement($owner, [&$name, new Identifier('alternate')]);
        $name = new Identifier('unrelated');

        self::assertSame('preferred', $requirement->keys[0]->value);
        self::assertSame($owner, $requirement->owner);
    }
}

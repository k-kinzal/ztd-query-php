<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyClosure;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(IndexDependencyClosure::class)]
#[Small]
final class IndexDependencyClosureTest extends TestCase
{
    public function testCheckKeepsOneSurvivorPerNativeSide(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('t')), ExternalRelationKind::BaseTable);
        $target = new IndexIdentity($owner, new Identifier('first_key'), IndexRole::Unique);
        $second = new IndexForeignKeyRequirement($owner, [new Identifier('first_KEY'), new Identifier('other')]);
        $last = new IndexForeignKeyRequirement($owner, [new Identifier('final_key')]);

        (new IndexDependencyClosure())->check([$target], [$second, $last]);

        self::assertSame('other', $second->keys[1]->value);
    }
}

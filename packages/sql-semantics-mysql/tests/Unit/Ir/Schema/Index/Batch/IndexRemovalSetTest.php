<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Batch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\IndexRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(IndexRemovalSet::class)]
#[Small]
final class IndexRemovalSetTest extends TestCase
{
    public function testCheckAcceptsEqualSeparateOwnerValues(): void
    {
        $table = new TableName(new Identifier('app'), new Identifier('t'));
        $a = new TableIdentity($table, ExternalRelationKind::BaseTable);
        $b = new TableIdentity($table, ExternalRelationKind::BaseTable);
        $targets = [new IndexIdentity($a, new Identifier('a'), IndexRole::Ordinary),
                    new IndexIdentity($b, new Identifier('b'), IndexRole::Ordinary)];
        $scope = new IndexRemovalSet();
        $scope->check($targets, new IndexChangePolicy());

        self::assertSame($scope->coordinate($a), $scope->coordinate($b));
    }

    public function testCoordinateCarriesTheSessionNamespace(): void
    {
        $table = new TableName(new Identifier('app'), new Identifier('t'));
        $owner = new TableIdentity($table, ExternalRelationKind::TemporaryTable);
        self::assertSame(['app', 't', ExternalRelationKind::TemporaryTable], (new IndexRemovalSet())->coordinate($owner));
    }
}

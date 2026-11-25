<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\DropDependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\DropTableDependencies;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\TableDropReference;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(DropTableDependencies::class)]
#[Medium]
final class DropTableDependenciesTest extends TestCase
{
    public function testCheckAdmitsACompleteModernCycle(): void
    {
        $schema = new Identifier('app');
        $first = new TableIdentity(new TableName($schema, new Identifier('first_key')), ExternalRelationKind::BaseTable);
        $second = new TableIdentity(new TableName($schema, new Identifier('second_key')), ExternalRelationKind::BaseTable);
        $edges = [new TableDropReference($first, $second), new TableDropReference($second, $first)];

        (new DropTableDependencies())->check([$first, $second], $edges, GrammarRelease::MySql8044);

        self::assertCount(2, $edges);
    }
}

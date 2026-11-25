<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IndexDependencyLowering::class)]
#[Medium]
final class IndexDependencyLoweringTest extends TestCase
{
    public function testResolveFindsIncomingCrossDatabaseSupport(): void
    {
        $catalog = (new Semantics())->context([
            'CREATE TABLE app.p (a INT, b INT, UNIQUE KEY current_key (a), UNIQUE KEY alternate (a))',
            'CREATE TABLE other_app.c (x INT, KEY supports (x), FOREIGN KEY (x) REFERENCES app.p (a))',
        ]);
        $inspection = (new Inspection())->inspect('DROP INDEX current_key ON app.p', $catalog);
        $statement = $inspection->statement;
        self::assertInstanceOf(DropIndex::class, $statement);
        $bound = $inspection->binding();
        $target = (new DropIndexLowering())->identity($bound, $statement, $statement->index);
        $requirements = (new IndexDependencyLowering())->resolve($bound, $statement, [$target]);

        self::assertCount(1, $requirements);
        self::assertSame('alternate', $requirements[0]->keys[1]->value);
    }

    public function testMatchesChecksTheOwningDatabase(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('t')), ExternalRelationKind::BaseTable);
        $lowering = new IndexDependencyLowering();

        self::assertTrue($lowering->matches(new QualifiedName(new Name('t'), new Name('app')), $owner));
        self::assertFalse($lowering->matches(new QualifiedName(new Name('t'), new Name('other')), $owner));
    }

    public function testRemainingUsesTheNativeOriginalName(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('t')), ExternalRelationKind::BaseTable);
        $first = new IndexDeclaration(new Name('GONE'), PhysicalIndexKind::Index, true);
        $other = new IndexDeclaration(new Name('survivor'), PhysicalIndexKind::Index, true);
        $target = new IndexIdentity($owner, new Identifier('gone'), IndexRole::Ordinary);

        self::assertSame([$other], (new IndexDependencyLowering())->remaining([$target], [$first, $other]));
    }
}

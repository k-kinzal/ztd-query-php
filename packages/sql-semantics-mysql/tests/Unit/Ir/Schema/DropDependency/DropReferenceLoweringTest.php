<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\DropDependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\DropReferenceLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(DropReferenceLowering::class)]
#[Medium]
final class DropReferenceLoweringTest extends TestCase
{
    public function testResolveIgnoresATemporaryParentShadow(): void
    {
        $catalog = (new Semantics())->context([
            'CREATE TABLE app.p (a INT PRIMARY KEY)',
            'CREATE TABLE app.c (a INT, FOREIGN KEY (a) REFERENCES p(a))',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $catalog);
        $shadow = new TableIdentity(new TableName(new Identifier('app'), new Identifier('p')), ExternalRelationKind::TemporaryTable);

        self::assertSame([], (new DropReferenceLowering())->resolve($catalog, [$shadow]));
    }

    public function testIncomingUsesTheExactPersistentSchema(): void
    {
        $catalog = (new Semantics())->context([
            'CREATE TABLE first_db.p (a INT PRIMARY KEY)',
            'CREATE TABLE next_db.p (a INT PRIMARY KEY)',
            'CREATE TABLE next_db.c (a INT, FOREIGN KEY (a) REFERENCES p(a))',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $catalog);
        $parent = new TableIdentity(new TableName(new Identifier('next_db'), new Identifier('p')), ExternalRelationKind::BaseTable);
        $edges = (new DropReferenceLowering())->incoming($catalog, $parent);

        self::assertCount(1, $edges);
        self::assertSame('next_db', $edges[0]->child->name->schema->value);
    }
}

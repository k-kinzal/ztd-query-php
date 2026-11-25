<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(DropIndexLowering::class)]
#[Medium]
final class DropIndexLoweringTest extends TestCase
{
    public function testLowerUsesTheVisibleTemporaryOwnerAndNativeCase(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE app.events (n INT, INDEX Élan (n))',
            'CREATE TEMPORARY TABLE app.events (n INT NOT NULL, UNIQUE INDEX ÉLAN (n))',
        ]);
        $drop = $semantics->analyze('DROP INDEX élan ON app.events ALGORITHM=DEFAULT LOCK=DEFAULT', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertSame(ExternalRelationKind::TemporaryTable, $drop->target->owner->kind);
        self::assertSame(IndexRole::Unique, $drop->target->role);
        self::assertSame('DROP INDEX `ÉLAN` ON `app`.`events`', $drop->toString());
    }

    public function testIndexKeepsAnImplicitClusteredKeyUnderItsDeclaredUniqueName(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.events (n INT NOT NULL, UNIQUE (n))']);
        self::assertInstanceOf(AnalysisContext::class, $context);

        $index = (new DropIndexLowering())->index($context->tables[0], new Name('N'));
        self::assertSame('n', $index->name->value);
        self::assertSame(PhysicalIndexKind::Unique, $index->kind);
    }
}

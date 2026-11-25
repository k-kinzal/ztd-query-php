<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\AlterMixedIndexesLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterMixedIndexesLowering::class)]
#[Medium]
final class AlterMixedIndexesLoweringTest extends TestCase
{
    public function testChangesKeepsOnlyIndexActionsAndTheirOrder(): void
    {
        $parsed = (new Inspection())->inspect('ALTER TABLE t LOCK=NONE, RENAME KEY by_a TO by_b, DROP KEY by_b')->statement;
        self::assertInstanceOf(AlterTable::class, $parsed);
        $actions = (new AlterMixedIndexesLowering())->changes($parsed);
        self::assertNotNull($actions);

        self::assertInstanceOf(RenameElement::class, $actions[0]);
        self::assertInstanceOf(DropElement::class, $actions[1]);
    }

    public function testLowerPublishesOnlyTheOrderedPhysicalChanges(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY a_key(a), KEY b_key(b), KEY stays(a,b))']);
        $value = $semantics->analyze('ALTER TABLE app.t DROP KEY B_KEY, RENAME INDEX A_KEY TO renamed', $catalog);
        self::assertInstanceOf(AlterIndexes::class, $value);

        self::assertSame('ALTER TABLE `app`.`t` DROP INDEX `b_key`, RENAME INDEX `a_key` TO `renamed`', $value->toString());
    }

    public function testUnmovedAppliesTheNativeCaseTable(): void
    {
        $source = new IndexDeclaration(new Name('Élan'), PhysicalIndexKind::Index, true);
        $stable = new IndexDeclaration(new Name('kept'), PhysicalIndexKind::Index, true);
        $names = (new AlterMixedIndexesLowering())->unmoved([$source, $stable], ['élan' => true]);

        self::assertCount(1, $names);
        self::assertSame('kept', $names[0]->value);
    }
}

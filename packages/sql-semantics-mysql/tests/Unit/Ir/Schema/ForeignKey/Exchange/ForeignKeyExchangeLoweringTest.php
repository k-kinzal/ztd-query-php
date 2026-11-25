<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyExchangeLowering::class)]
#[Medium]
final class ForeignKeyExchangeLoweringTest extends TestCase
{
    public function testActionsSelectsTheNativeClausesAndExcludesThePolicyFromTheirOrder(): void
    {
        $sql = 'ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a), ALGORITHM=COPY, DROP FOREIGN KEY prior_symbol';
        $operation = NativeAdditionFixture::operation($sql);
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        $actions = (new ForeignKeyExchangeLowering())->actions($operation->statement);
        self::assertNotNull($actions);
        self::assertCount(2, $actions);
        self::assertInstanceOf(ForeignKey::class, $actions[0]);
        self::assertInstanceOf(DropElement::class, $actions[1]);
    }

    public function testLowerRetainsTheOriginalNativeOrdinalWhenItsEdgeIsRemoved(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE app.parent (a INT PRIMARY KEY, b INT NOT NULL UNIQUE)',
            'CREATE TABLE app.child (x INT, y INT, CONSTRAINT child_ibfk_9 FOREIGN KEY (x) REFERENCES parent(a))',
        ]);
        $root = $semantics->analyze('ALTER TABLE app.child DROP FOREIGN KEY CHILD_IBFK_9, ADD FOREIGN KEY (y) REFERENCES parent(b)', $context);

        self::assertInstanceOf(AlterForeignKeys::class, $root);
        self::assertInstanceOf(ForeignKeyRemoval::class, $root->actions[0]);
        self::assertSame('child_ibfk_9', $root->actions[0]->name?->value);
        self::assertInstanceOf(ForeignKeyAddition::class, $root->actions[1]);
        self::assertSame('child_ibfk_10', $root->actions[1]->symbol->value);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionLowering;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyAdditionLowering::class)]
#[Medium]
final class ForeignKeyAdditionLoweringTest extends TestCase
{
    public function testAdditionsKeepsTheOrderedNativeForeignConstraints(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a), ADD CONSTRAINT Other FOREIGN KEY(y) REFERENCES parent(b)');
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        $added = (new ForeignKeyAdditionLowering())->additions($operation->statement);

        self::assertNotNull($added);
        self::assertCount(2, $added);
    }

    public function testLowerUsesThePersistentParentFromTheChildDatabase(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD CONSTRAINT Named FOREIGN KEY(x) REFERENCES parent(a)');
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        $root = (new ForeignKeyAdditionLowering())->lower($operation, $operation->statement);

        self::assertSame('`app`.`parent`', $root->additions[0]->parent->quoted());
        self::assertSame('Named', $root->additions[0]->createdIndex?->value);
    }

    public function testIdentifierDistinguishesAnExplicitEmptySymbolFromAllocation(): void
    {
        $lowering = new ForeignKeyAdditionLowering();

        self::assertSame('', $lowering->identifier(new Name(''))?->value);
        self::assertNull($lowering->identifier(null));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPairLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Testing\MySql\Inspection;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyPairLowering::class)]
#[Medium]
final class ForeignKeyPairLoweringTest extends TestCase
{
    public function testPairsSettlesCaseAndKeepsNamedFieldsInOrder(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(Y, X) REFERENCES parent(B, A)');
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        $command = $operation->statement->commands[0];
        self::assertInstanceOf(AddConstraint::class, $command);
        self::assertInstanceOf(ForeignKey::class, $command->element);
        $parents = $operation->context->tables;
        $pairs = (new ForeignKeyPairLowering())->pairs($command->element, $parents[1], $parents[0], GrammarRelease::MySql910);

        self::assertSame(['y', 'x'], array_map(static fn ($pair): string => $pair->child->value, $pairs));
        self::assertSame(['b', 'a'], array_map(static fn ($pair): string => $pair->parent->value, $pairs));
    }

    public function testPairRetainsTheNativeDirection(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        $tables = $operation->context->tables;
        $part = new ColumnPart(new Name('Y'), direction: Direction::Descending);
        $pair = (new ForeignKeyPairLowering())->pair($part, new Name('A'), $tables[1], $tables[0], GrammarRelease::MySql910);

        self::assertSame('y', $pair->child->value);
        self::assertTrue($pair->descending);
    }

    public function testColumnBindsTheCanonicalDeclaredName(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        $column = (new ForeignKeyPairLowering())->column($operation->context->tables[1], new Name('Y'));

        self::assertSame('y', $column->name->value);
    }

    public function testIntegerKeepsTheNativeWidthAndSign(): void
    {
        $type = new Integral(IntegralKind::MediumInt, '5', [NumericModifier::Unsigned]);

        self::assertSame([IntegerStorage::Medium, true], (new ForeignKeyPairLowering())->integer($type));
    }

    public function testDomainPreservesBothNativeStringStorageRoles(): void
    {
        $ddl = 'CREATE TABLE app.strings (x VARBINARY(8), a BINARY(4))';
        $table = (new Inspection())->inspect($ddl)->declarations()[0];
        $domain = (new ForeignKeyPairLowering())->domain($table->columns[0], $table->columns[1], GrammarRelease::MySql910);

        self::assertInstanceOf(ForeignKeyStringDomain::class, $domain);
        self::assertSame(8, $domain->child->length);
        self::assertSame(4, $domain->parent->length);
    }

    public function testFieldRetainsTheExactNativeScalarType(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE app.fields (x TIME(3))')->declarations()[0];
        $field = (new ForeignKeyPairLowering())->field($table->columns[0], GrammarRelease::MySql910);

        self::assertEquals(new ForeignKeyScalarStorage(ForeignKeyScalarKind::Time, 3), $field);
    }

    public function testDomainPreservesTheTwoDifferentPackedFieldTypes(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE app.fields (x BIT(9), y DECIMAL(10,2))')->declarations()[0];
        $domain = (new ForeignKeyPairLowering())->domain($table->columns[0], $table->columns[1], GrammarRelease::MySql910);

        self::assertInstanceOf(ForeignKeyScalarDomain::class, $domain);
        self::assertEquals(new ForeignKeyScalarStorage(ForeignKeyScalarKind::Bit, 9), $domain->child);
        self::assertEquals(new ForeignKeyScalarStorage(ForeignKeyScalarKind::Decimal, 10, 2), $domain->parent);
    }
}

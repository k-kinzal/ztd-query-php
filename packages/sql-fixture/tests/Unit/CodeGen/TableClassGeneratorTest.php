<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\CodeGen\PhpIdentifier;
use SqlFixture\CodeGen\PhpTypeMapper;
use SqlFixture\CodeGen\TableClassGenerator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\CodeGen\SampleSchemas;

#[CoversClass(TableClassGenerator::class)]
#[UsesClass(PhpIdentifier::class)]
#[UsesClass(PhpTypeMapper::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class TableClassGeneratorTest extends TestCase
{
    #[Test]
    public function namesTheClassAfterTheTable(): void
    {
        self::assertSame('OrderDetail', (new TableClassGenerator())->className(SampleSchemas::orderDetail()));
    }

    #[Test]
    public function theGeneratedCodeParses(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertNotFalse(@token_get_all($code, TOKEN_PARSE));
    }

    #[Test]
    public function declaresTheNamespaceAndStrictTypes(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString('declare(strict_types=1);', $code);
        self::assertStringContainsString('namespace Demo\\Fixtures;', $code);
        self::assertStringContainsString('final class OrderDetail', $code);
    }

    #[Test]
    public function carriesTheTableAndColumnNamesAsConstants(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString("public const TABLE = 'order_detail';", $code);
        self::assertStringContainsString("public const ORDER_ID = 'order_id';", $code);
    }

    #[Test]
    public function handsOutColumnRefsForBuildingPlans(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString('public static function orderId(): ColumnRef', $code);
        self::assertStringContainsString('return ColumnRef::of(self::TABLE, self::ORDER_ID);', $code);
    }

    #[Test]
    public function takesOverridesAsNamedArguments(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString('public static function overrides(', $code);
        self::assertStringContainsString('?int $orderId = null,', $code);
        self::assertStringContainsString('?int $quantity = null,', $code);
    }

    #[Test]
    public function documentsTheRowAsAnArrayShape(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString('@phpstan-type OrderDetailRow array{', $code);
        self::assertStringContainsString('order_id: int,', $code);
    }

    #[Test]
    public function documentsAnEnumAsItsOwnValues(): void
    {
        $schema = new TableSchema('order', [
            'status' => new ColumnDefinition('status', 'ENUM', nullable: false, enumValues: ['paid', 'pending']),
        ]);

        $code = (new TableClassGenerator())->generate($schema, 'Demo\\Fixtures');

        self::assertStringContainsString("status: 'paid'|'pending',", $code);
        self::assertStringContainsString("@param 'paid'|'pending'|null \$status", $code);
    }

    #[Test]
    public function readsRowsBackWithTheirDocumentedShape(): void
    {
        $code = (new TableClassGenerator())->generate(SampleSchemas::orderDetail(), 'Demo\\Fixtures');

        self::assertStringContainsString('@return list<OrderDetailRow>', $code);
        self::assertStringContainsString('public static function rows(FixtureSet $fixtures): array', $code);
        self::assertStringContainsString('@return OrderDetailRow|null', $code);
    }

    #[Test]
    public function aColumnOfUnknownTypeIsLeftUntyped(): void
    {
        $schema = new TableSchema('odd', ['thing' => new ColumnDefinition('thing', 'SOMETHING_ODD')]);

        $code = (new TableClassGenerator())->generate($schema, 'Demo\\Fixtures');

        self::assertStringContainsString('mixed $thing = null,', $code);
        self::assertNotFalse(@token_get_all($code, TOKEN_PARSE));
    }

    #[Test]
    public function aTableNamedAfterAReservedWordStillGenerates(): void
    {
        $schema = new TableSchema('class', ['list' => new ColumnDefinition('list', 'INT')]);

        $code = (new TableClassGenerator())->generate($schema, 'Demo\\Fixtures');

        self::assertStringContainsString('final class ClassTable', $code);
        self::assertStringContainsString('$listValue', $code);
        self::assertNotFalse(@token_get_all($code, TOKEN_PARSE));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\CodeGen\PhpIdentifier;
use SqlFixture\CodeGen\PhpTypeMapper;
use SqlFixture\CodeGen\SchemaCodeGenerator;
use SqlFixture\CodeGen\TableClassGenerator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\CodeGen\SampleSchemas;

#[CoversClass(SchemaCodeGenerator::class)]
#[UsesClass(TableClassGenerator::class)]
#[UsesClass(PhpIdentifier::class)]
#[UsesClass(PhpTypeMapper::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class SchemaCodeGeneratorTest extends TestCase
{
    #[Test]
    public function generatesOneFilePerTable(): void
    {
        $files = (new SchemaCodeGenerator())->generate(SampleSchemas::pair(), 'Demo\\Fixtures');

        self::assertSame(['Customer.php', 'Order.php'], array_keys($files));
    }

    #[Test]
    public function eachFileHoldsItsOwnClass(): void
    {
        $files = (new SchemaCodeGenerator())->generate(SampleSchemas::pair(), 'Demo\\Fixtures');

        self::assertStringContainsString('final class Order', $files['Order.php']);
        self::assertStringContainsString('final class Customer', $files['Customer.php']);
    }

    #[Test]
    public function writesTheFilesAndCreatesTheDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/sql-fixture-codegen-' . uniqid();

        $written = (new SchemaCodeGenerator())->write(SampleSchemas::pair(), 'Demo\\Fixtures', $directory);

        self::assertCount(2, $written);
        self::assertFileExists($directory . '/Order.php');
        self::assertStringContainsString('final class Order', (string) file_get_contents($directory . '/Order.php'));
    }

    #[Test]
    public function writingTwiceOverwritesRatherThanAppends(): void
    {
        $directory = sys_get_temp_dir() . '/sql-fixture-codegen-' . uniqid();
        $generator = new SchemaCodeGenerator();

        $generator->write(SampleSchemas::pair(), 'Demo\\Fixtures', $directory);
        $generator->write(SampleSchemas::pair(), 'Demo\\Fixtures', $directory);

        $contents = (string) file_get_contents($directory . '/Order.php');
        self::assertSame(1, substr_count($contents, 'final class Order'));
    }
}

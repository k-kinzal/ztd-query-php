<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTextSample;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(MySqlTextSample::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlTextSampleTest extends TestCase
{
    public function testCharFillsExactlyTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('c', 'CHAR', length: 9);

        self::assertSame(9, strlen((new MySqlTextSample())->char(Factory::create(), $column)));
    }

    public function testCharWithNoLengthDeclaredHoldsOneCharacter(): void
    {
        self::assertSame(1, strlen((new MySqlTextSample())->char(Factory::create(), new ColumnDefinition('c', 'CHAR'))));
    }

    public function testVarcharStaysWithinTheDeclaredLength(): void
    {
        $column = new ColumnDefinition('v', 'VARCHAR', length: 12);

        self::assertLessThanOrEqual(12, strlen((new MySqlTextSample())->varchar(Factory::create(), $column)));
    }

    public function testVarcharWithNoLengthDeclaredStaysWithinTheDefault(): void
    {
        $column = new ColumnDefinition('v', 'VARCHAR');

        self::assertLessThanOrEqual(255, strlen((new MySqlTextSample())->varchar(Factory::create(), $column)));
    }
    public function testVarcharDrawsEnoughTextToFillTheDeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlTextSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR', length: 40));

        self::assertSame([[40]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharDrawsTheShortestTextFakerWillGiveForAVeryNarrowColumn(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlTextSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR', length: 3));

        self::assertSame([[5]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharDrawsTwoHundredCharactersForAVeryWideColumn(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlTextSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR', length: 4000));

        self::assertSame([[200]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharDrawsTwoHundredCharactersWhereNoLengthIsDeclared(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlTextSample())->varchar($faker, new ColumnDefinition('v', 'VARCHAR'));

        self::assertSame([[200]], $faker->methodCalls['text'] ?? []);
    }

    public function testVarcharCutsTheDrawDownToAColumnNarrowerThanFakerWillDraw(): void
    {
        $written = (new MySqlTextSample())->varchar(Factory::create(), new ColumnDefinition('v', 'VARCHAR', length: 3));

        self::assertLessThanOrEqual(3, strlen($written));
    }
}

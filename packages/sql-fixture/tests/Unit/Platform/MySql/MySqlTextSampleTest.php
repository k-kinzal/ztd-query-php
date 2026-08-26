<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTextSample;
use SqlFixture\Schema\ColumnDefinition;

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
}

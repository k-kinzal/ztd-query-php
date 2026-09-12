<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Value\ColumnGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
final class TypeMapperInterfaceTest extends TestCase
{
    public function testGenerateReturnsAValueForTheColumn(): void
    {
        $mapper = new \SqlFixture\Platform\Sqlite\SqliteTypeMapper();
        $value = $mapper->generate(Factory::create(), new ColumnDefinition('code', 'VARCHAR', length: 8, nullable: false));
        self::assertIsString($value);
        self::assertLessThanOrEqual(8, strlen($value));
    }
}

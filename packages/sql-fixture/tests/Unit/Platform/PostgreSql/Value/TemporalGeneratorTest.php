<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Value\TemporalGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Value\StructuredGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class TemporalGeneratorTest extends TestCase
{
    public function testGenerateDateUsesDatabaseFormat(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (new Subject())->generate(Factory::create(), new ColumnDefinition('created', 'DATE')));
    }

    public function testGenerateTimeUsesDatabaseFormat(): void
    {
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', (new Subject())->generate(Factory::create(), new ColumnDefinition('created', 'TIME')));
    }

    public function testGenerateTimestampUsesDatabaseFormat(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (new Subject())->generate(Factory::create(), new ColumnDefinition('created', 'TIMESTAMP')));
    }
}

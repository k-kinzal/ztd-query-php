<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Validation\OverrideValidator as Subject;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TableSchema::class)]
final class OverrideValidatorTest extends TestCase
{
    public function testAssertOverridesFitSchemaAcceptsNullableAndExplicitKeys(): void
    {
        $schema = new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true), 'name' => new ColumnDefinition('name', 'TEXT', nullable: true)], ['id']);
        (new Subject())->assertOverridesFitSchema($schema, ['id' => 10, 'name' => null]);
        self::assertSame(['id', 'name'], array_keys($schema->columns));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaResolverInterface as Subject;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaNotFoundException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(StaticSchemaResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableIdentifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TableSchema::class)]
final class SchemaResolverInterfaceTest extends TestCase
{
    public function testResolveReturnsTheRegisteredSchema(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT')]);
        $resolver = new StaticSchemaResolver([$schema]);
        self::assertSame($schema, $resolver->resolve('users'));
    }

    public function testHasReportsAvailabilityBeforeResolution(): void
    {
        $resolver = new StaticSchemaResolver([new TableSchema('users', [])]);
        self::assertTrue($resolver->has('users'));
        self::assertFalse($resolver->has('missing'));
    }
}

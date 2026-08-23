<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(StaticSchemaResolver::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(SchemaNotFoundException::class)]
final class StaticSchemaResolverTest extends TestCase
{
    #[Test]
    public function resolvesARegisteredSchema(): void
    {
        $schema = new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT')]);

        self::assertSame($schema, (new StaticSchemaResolver([$schema]))->resolve('order'));
    }

    #[Test]
    public function resolvesIgnoringCaseQuotingAndSchemaQualifier(): void
    {
        $schema = new TableSchema('Order', ['id' => new ColumnDefinition('id', 'INT')]);
        $resolver = new StaticSchemaResolver([$schema]);

        self::assertSame($schema, $resolver->resolve('ORDER'));
        self::assertSame($schema, $resolver->resolve('`shop`.`order`'));
    }

    #[Test]
    public function registerAddsAfterConstruction(): void
    {
        $resolver = new StaticSchemaResolver();
        $resolver->register(new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT')]));

        self::assertTrue($resolver->has('order'));
    }

    #[Test]
    public function registerReplacesASchemaOfTheSameName(): void
    {
        $resolver = new StaticSchemaResolver([
            new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT')]),
        ]);
        $replacement = new TableSchema('order', ['no' => new ColumnDefinition('no', 'INT')]);
        $resolver->register($replacement);

        self::assertSame($replacement, $resolver->resolve('order'));
        self::assertCount(1, $resolver->tableNames());
    }

    #[Test]
    public function hasReportsAnUnknownTable(): void
    {
        self::assertFalse((new StaticSchemaResolver())->has('order'));
    }

    #[Test]
    public function tableNamesAreLowerCased(): void
    {
        $resolver = new StaticSchemaResolver([
            new TableSchema('Order', ['id' => new ColumnDefinition('id', 'INT')]),
        ]);

        self::assertSame(['order'], $resolver->tableNames());
    }

    #[Test]
    public function resolveThrowsListingWhatIsKnown(): void
    {
        $resolver = new StaticSchemaResolver([
            new TableSchema('customer', ['id' => new ColumnDefinition('id', 'INT')]),
        ]);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('Known tables: customer');

        $resolver->resolve('order');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaResolverInterface;
use SqlFixture\Schema\TableSchema;

#[CoversNothing]
final class SchemaResolverInterfaceTest extends TestCase
{
    public function testResolveAnswersTheTableItWasAskedFor(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'int')], ['id']);
        $resolver = self::createStub(SchemaResolverInterface::class);
        $resolver->method('resolve')->willReturn($schema);

        self::assertSame($schema, $resolver->resolve('users'));
    }

    public function testHasReportsWhetherATableCanBeResolved(): void
    {
        $resolver = self::createStub(SchemaResolverInterface::class);
        $resolver->method('has')->willReturn(false);

        self::assertFalse($resolver->has('users'));
    }
}

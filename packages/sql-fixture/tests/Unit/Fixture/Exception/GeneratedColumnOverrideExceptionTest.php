<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
final class GeneratedColumnOverrideExceptionTest extends TestCase
{
    public function testDescribesGeneratedColumnOverride(): void
    {
        $schema = new TableSchema('order', ['code' => new ColumnDefinition('code', 'VARCHAR', generated: true)]);
        $exception = new \SqlFixture\Fixture\Exception\GeneratedColumnOverrideException('code', $schema);


        $message = $exception->getMessage();

        self::assertSame(
            'Cannot override order.code: the database computes it, so a value written here '
            . 'would be rejected on insert.',
            $message
        );
        self::assertSame('code', $exception->column);
        self::assertSame($schema, $exception->schema);
    }
}

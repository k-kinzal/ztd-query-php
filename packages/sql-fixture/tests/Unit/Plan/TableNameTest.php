<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\TableName;

#[CoversClass(TableName::class)]
#[UsesClass(PlanSyntaxException::class)]
final class TableNameTest extends TestCase
{
    #[DataProvider('providerWrittenName')]
    public function testOfReadsATableNameWithoutItsQuotes(string $written, string $expected): void
    {
        self::assertSame($expected, TableName::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerWrittenName(): iterable
    {
        yield 'bare' => ['order', 'order'];
        yield 'padded' => ['  order  ', 'order'];
        yield 'backquoted' => ['`order`', 'order'];
        yield 'double quoted' => ['"order"', 'order'];
        yield 'underscored' => ['_order$2', '_order$2'];
    }

    #[DataProvider('providerNotATableName')]
    public function testOfRefusesTextThatIsNotATableName(string $written): void
    {
        $this->expectException(PlanSyntaxException::class);

        TableName::of($written);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNotATableName(): iterable
    {
        yield 'empty' => [''];
        yield 'a relation missing its dot' => ['order id < order_detail order_id'];
        yield 'an endpoint' => ['order.id'];
        yield 'starting with a digit' => ['1order'];
    }
}

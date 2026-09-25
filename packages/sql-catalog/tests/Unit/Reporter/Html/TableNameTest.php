<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\TableName;

#[CoversClass(TableName::class)]
final class TableNameTest extends TestCase
{
    /**
     * @return list<array{string, string|null}>
     */
    public static function providerSchema(): array
    {
        return [['app.users', 'app'], ['users', null], ['{$}.users', null], ['.users', null]];
    }

    #[DataProvider('providerSchema')]
    public function testSchemaIsTheQualifierWhenOneIsKnown(string $name, ?string $expected): void
    {
        self::assertSame($expected, (new TableName($name))->schema());
    }

    public function testLocalDropsTheSchema(): void
    {
        self::assertSame('users', (new TableName('app.users'))->local());
        self::assertSame('users', (new TableName('users'))->local());
    }

    public function testHasGapSaysWhetherPartOfTheNameIsUnknown(): void
    {
        self::assertTrue((new TableName('{$}posts'))->hasGap());
        self::assertFalse((new TableName('posts'))->hasGap());
    }

    public function testIsUnknownOnlyWhenNothingOfTheNameIsKnown(): void
    {
        self::assertTrue((new TableName('{$}'))->isUnknown());
        self::assertFalse((new TableName('{$}posts'))->isUnknown());
    }

    public function testLabelExplainsAWhollyUnknownName(): void
    {
        self::assertSame('table not pinned down', (new TableName('{$}'))->label());
        self::assertSame('{$}posts', (new TableName('{$}posts'))->label());
    }
}

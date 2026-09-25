<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Execution\IndexCaches::class)]
#[Medium]
final class IndexCachesTest extends TestCase
{
    #[TestWith(['CACHE INDEX t IN DEFAULT', 'CACHE INDEX `t` IN DEFAULT'])]
    #[TestWith(['CACHE INDEX t PARTITION (ALL) KEY (PRIMARY) IN hot', 'CACHE INDEX `t` PARTITION(ALL) INDEX(`PRIMARY`) IN `hot`'])]
    #[TestWith(['LOAD INDEX INTO CACHE t', 'LOAD INDEX INTO CACHE `t`'])]
    #[TestWith(['LOAD INDEX INTO CACHE t PARTITION (p0) IGNORE LEAVES', 'LOAD INDEX INTO CACHE `t` PARTITION(`p0`) IGNORE LEAVES'])]
    public function testWriteKeepsTheCommandAndItsRequiredOperands(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testTargetPreservesQuotedPartitionAndIndexIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t PARTITION (`a``b`) KEY (`c``d`) IN DEFAULT');
        self::assertSame('CACHE INDEX `t` PARTITION(`a``b`) INDEX(`c``d`) IN DEFAULT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testPreloadKeepsEachLeafSelectionNextToItsTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('LOAD INDEX INTO CACHE t IGNORE LEAVES, u');
        self::assertSame('LOAD INDEX INTO CACHE `t` IGNORE LEAVES, `u`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testNamesAllowsAnExplicitEmptyIndexRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t INDEX () IN DEFAULT');
        self::assertSame('CACHE INDEX `t` INDEX() IN DEFAULT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

}

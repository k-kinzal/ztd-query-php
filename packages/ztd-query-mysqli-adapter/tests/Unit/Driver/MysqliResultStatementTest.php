<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliResultStatementTest extends TestCase
{
    public function testItIsTheStatementZtdReadsResultsThrough(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertContains(StatementInterface::class, class_implements($stmt));
    }

    public function testExecuteAlwaysReturnsTrue(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertTrue($stmt->execute());
        self::assertTrue($stmt->execute([1, 2, 3]));
    }

    public function testFetchAllReturnsEmptyArrayWhenResultIsNull(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertSame([], $stmt->fetchAll());
    }

    public function testFetchAllReturnsRowsFromResult(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = StubMysqliResult::create($expected);

        $stmt = new MysqliResultStatement($result, 2);

        self::assertSame($expected, $stmt->fetchAll());
    }

    public function testRowCountReturnsAffectedRows(): void
    {
        $stmt = new MysqliResultStatement(null, 5);

        self::assertSame(5, $stmt->rowCount());
    }

    public function testRowCountReturnsZeroForNoAffectedRows(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertSame(0, $stmt->rowCount());
    }

    public function testSaturatesAffectedRowsOutsideThePlatformIntegerRange(): void
    {
        $stmt = new MysqliResultStatement(null, '999999999999999999999999999999999999');

        self::assertSame(PHP_INT_MAX, $stmt->rowCount());
    }

    public function testResultColumnsReturnsEmptyArrayWithoutResult(): void
    {
        $stmt = new MysqliResultStatement(null, 0);
        $resolver = self::createStub(ResultColumnTypeResolver::class);

        self::assertSame([], $stmt->resultColumns($resolver));
    }

    public function testResultColumnsMapMysqliFieldMetadata(): void
    {
        $integer = new StubMysqliField('id', MYSQLI_TYPE_LONG, '63');
        $text = new StubMysqliField('description', MYSQLI_TYPE_BLOB, '255');
        $binary = new StubMysqliField('payload', MYSQLI_TYPE_BLOB, '63');
        $result = StubMysqliResult::create([], [$integer, $text, $binary]);

        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            static fn (array $metadata): ColumnDeclaration => match ($metadata['name'] ?? '') {
                'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                'description' => new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
                default => new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BLOB'),
            },
        );
        $columns = (new MysqliResultStatement($result, 0))->resultColumns($resolver);

        self::assertSame(['id', 'description', 'payload'], array_map(static fn ($column) => $column->name, $columns));
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
        self::assertSame(ColumnTypeFamily::TEXT, $columns[1]->type->family);
        self::assertSame(ColumnTypeFamily::BINARY, $columns[2]->type->family);
    }

}

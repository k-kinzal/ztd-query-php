<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultColumnExtractor::class)]
final class MysqliResultColumnExtractorTest extends TestCase
{
    public function testExtractPreservesEveryResultColumn(): void
    {
        $result = StubMysqliResult::create([], [
            new StubMysqliField('id', MYSQLI_TYPE_LONG, 63),
            new StubMysqliField('name', MYSQLI_TYPE_VAR_STRING, 255),
        ]);

        $columns = MysqliResultColumnExtractor::extract($result);

        self::assertSame(['id', 'name'], array_map(static fn ($column) => $column->name, $columns));
        self::assertSame(ColumnTypeFamily::UNKNOWN, $columns[0]->type->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $columns[1]->type->family);
    }

    public function testExtractDelegatesRawFieldMetadataToResolver(): void
    {
        $field = new StubMysqliField('value', MYSQLI_TYPE_LONG, '63');
        $result = StubMysqliResult::create([], [$field]);
        $resolver = self::createMock(ResultColumnTypeResolver::class);
        $resolver->expects(self::once())->method('resolve')->with([
            'name' => 'value',
            'type' => MYSQLI_TYPE_LONG,
            'charsetnr' => '63',
        ])->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'));

        $columns = MysqliResultColumnExtractor::extract($result, $resolver);

        self::assertSame('value', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
    }
}

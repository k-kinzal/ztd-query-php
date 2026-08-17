<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MysqliFieldTypeProvider;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
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
    }

    #[DataProviderExternal(MysqliFieldTypeProvider::class, 'provide')]
    public function testExtractMapsEveryMysqliFieldType(
        int $fieldType,
        int|string $charsetNumber,
        ColumnTypeFamily $expectedFamily,
    ): void {
        $field = new StubMysqliField('value', $fieldType, $charsetNumber);
        $result = StubMysqliResult::create([], [$field]);

        $columns = MysqliResultColumnExtractor::extract($result);

        self::assertSame('value', $columns[0]->name);
        self::assertSame($expectedFamily, $columns[0]->type->family);
    }
}

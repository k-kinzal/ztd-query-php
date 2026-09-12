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
    public function testExtractDelegatesRawFieldMetadataToResolver(): void
    {
        $field = new StubMysqliField('value', MYSQLI_TYPE_LONG, '63');
        $result = StubMysqliResult::create([], [$field]);
        $resolver = new class () implements ResultColumnTypeResolver {
            public function resolve(array $metadata): ColumnType
            {
                TestCase::assertSame(['name' => 'value', 'type' => MYSQLI_TYPE_LONG, 'charsetnr' => '63'], $metadata);
                return new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER');
            }
        };

        $columns = MysqliResultColumnExtractor::extract($result, $resolver);

        self::assertSame('value', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
    }

    public function testExtractReturnsEveryResultColumn(): void
    {
        $result = StubMysqliResult::create([], [
            new StubMysqliField('id', MYSQLI_TYPE_LONG, 63),
            new StubMysqliField('name', MYSQLI_TYPE_VAR_STRING, 255),
        ]);
        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::STRING, 'TEXT'));

        $columns = MysqliResultColumnExtractor::extract($result, $resolver);

        self::assertSame(['id', 'name'], array_column($columns, 'name'));
    }
}

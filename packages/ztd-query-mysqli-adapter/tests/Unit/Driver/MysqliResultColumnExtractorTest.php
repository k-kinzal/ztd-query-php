<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\RecordingColumnTypeResolver;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultColumnExtractor::class)]
final class MysqliResultColumnExtractorTest extends TestCase
{
    public function testExtractFailsWithoutDialectResolver(): void
    {
        $result = StubMysqliResult::create([], [
            new StubMysqliField('id', MYSQLI_TYPE_LONG, 63),
            new StubMysqliField('name', MYSQLI_TYPE_VAR_STRING, 255),
        ]);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A database platform result column type resolver is required.');

        MysqliResultColumnExtractor::extract($result, new MissingResultColumnTypeResolver());
    }

    public function testExtractDelegatesRawFieldMetadataToResolver(): void
    {
        $field = new StubMysqliField('value', MYSQLI_TYPE_LONG, '63');
        $result = StubMysqliResult::create([], [$field]);
        $resolver = new RecordingColumnTypeResolver(new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'));

        $columns = MysqliResultColumnExtractor::extract($result, $resolver);

        self::assertSame(
            [['name' => 'value', 'type' => MYSQLI_TYPE_LONG, 'charsetnr' => '63']],
            $resolver->metadataSeen,
        );

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
        $resolver->method('resolve')->willReturn(new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT'));

        $columns = MysqliResultColumnExtractor::extract($result, $resolver);

        self::assertSame(['id', 'name'], array_column($columns, 'name'));
    }
}

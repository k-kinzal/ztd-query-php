<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\MysqliStatement;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $stmt = StubMysqliStmt::create();
        $mysqli = new StubMysqli();

        $statement = new MysqliStatement($stmt, $mysqli);

        self::assertInstanceOf(StatementInterface::class, $statement);
    }

    public function testResultColumnsLoadPreparedResultMetadata(): void
    {
        $field = new StubMysqliField('id', MYSQLI_TYPE_LONG, '63');
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = StubMysqliResult::create([], [$field]);

        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $columns = (new MysqliStatement($stmt, new StubMysqli()))->resultColumns($resolver);

        self::assertSame('id', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
    }
}

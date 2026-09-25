<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\Reports;

#[CoversClass(Reports::class)]
#[Medium]
final class ReportsTest extends TestCase
{
    #[TestWith(['SHOW ENGINE INNODB STATUS', 'SHOW ENGINE `INNODB` STATUS'])]
    #[TestWith(["SHOW ENGINE 'a b' MUTEX", 'SHOW ENGINE `a b` MUTEX'])]
    #[TestWith(['SHOW ENGINE ALL LOGS', 'SHOW ENGINE ALL LOGS'])]
    #[TestWith(['SHOW PROFILES', 'SHOW PROFILES'])]
    #[TestWith(['SHOW PROFILE', 'SHOW PROFILE'])]
    #[TestWith(['SHOW PROFILE ALL, BLOCK IO FOR QUERY 7 LIMIT 1, 2', 'SHOW PROFILE ALL, BLOCK IO FOR QUERY 7 LIMIT 2 OFFSET 1'])]
    #[TestWith(['SHOW PARSE_TREE SHOW SCHEMAS', 'SHOW PARSE_TREE SHOW DATABASES'])]
    public function testWriteQuotesEngineNamesAndNormalizesWindows(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, Reports::write($statement)?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        self::assertNull(Reports::write($binder->bind('SHOW DATABASES')));
    }

    public function testLimitWritesTheCountBeforeTheOptionalOffset(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $window = $binder->bind('SHOW PROFILE LIMIT 4, 9');
        $count = $binder->bind('SHOW PROFILE LIMIT ?');
        self::assertInstanceOf(ShowProfileStatement::class, $window);
        self::assertInstanceOf(ShowProfileStatement::class, $count);
        self::assertSame([], Reports::limit(null));
        self::assertSame('LIMIT 9 OFFSET 4', implode(' ', array_map(static fn (\SqlSemantics\Model\Sql\Tree $part): string => $part->toString(), Reports::limit($window->limit))));
        self::assertSame('LIMIT ?', implode(' ', array_map(static fn (\SqlSemantics\Model\Sql\Tree $part): string => $part->toString(), Reports::limit($count->limit))));
    }
}

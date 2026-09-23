<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Maintenance\MySql\AnalyzeTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BinlogPolicy::class)]
#[Medium]
final class BinlogPolicyTest extends TestCase
{
    #[TestWith(['TABLE t', BinlogPolicy::Write])]
    #[TestWith(['LOCAL TABLE t', BinlogPolicy::Omit])]
    #[TestWith(['NO_WRITE_TO_BINLOG TABLE t', BinlogPolicy::Omit])]
    public function testClassifiesTheOperationSpecificSelection(string $suffix, BinlogPolicy $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ANALYZE ' . $suffix);
        self::assertInstanceOf(AnalyzeTablesStatement::class, $statement);
        self::assertSame($expected, $statement->binlog);
    }
}

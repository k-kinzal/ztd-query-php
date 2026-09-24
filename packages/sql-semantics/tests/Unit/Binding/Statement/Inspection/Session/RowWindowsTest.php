<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Session\RowWindows;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowWindows::class)]
#[Medium]
final class RowWindowsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testReadListsTheCommaOffsetFirst(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SHOW WARNINGS LIMIT 18446744073709551615, ?');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertInstanceOf(Parameter::class, $statement->limit?->count);
        self::assertSame('18446744073709551615', $statement->limit->offset?->spelling());
    }

    public function testReadListsTheCountBeforeOffset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINLOG EVENTS LIMIT 4 OFFSET 9');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertSame(['4', '9'], [$statement->limit?->count->spelling(), $statement->limit?->offset?->spelling()]);
    }

    public function testReadReturnsNullWithoutLimit(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ERRORS');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertNull($statement->limit);
    }
}

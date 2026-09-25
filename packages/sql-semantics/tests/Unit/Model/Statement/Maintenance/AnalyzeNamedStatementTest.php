<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\AnalyzeNamedStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnalyzeNamedStatement::class)]
#[Medium]
final class AnalyzeNamedStatementTest extends TestCase
{
    public function testBindsAQualifiedTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('ANALYZE main.t');
        self::assertInstanceOf(AnalyzeNamedStatement::class, $statement);
        self::assertSame(['main', 't'], $statement->target->parts);
        self::assertSame(StatementKind::Analyze, $statement->kind);
        self::assertSame('ANALYZE "main"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('ANALYZE t');
        self::assertInstanceOf(AnalyzeNamedStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->target, $copy->target);
        self::assertSame('ANALYZE "t"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}

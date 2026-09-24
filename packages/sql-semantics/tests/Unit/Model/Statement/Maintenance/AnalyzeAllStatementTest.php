<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\AnalyzeAllStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnalyzeAllStatement::class)]
#[Medium]
final class AnalyzeAllStatementTest extends TestCase
{
    public function testBindsAnUnqualifiedAnalysis(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('ANALYZE');
        self::assertInstanceOf(AnalyzeAllStatement::class, $statement);
        self::assertSame(StatementKind::Analyze, $statement->kind);
        self::assertSame('ANALYZE', $statement->toString());
    }

    public function testWithOriginReplacesProvenanceOnly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('ANALYZE');
        self::assertInstanceOf(AnalyzeAllStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->source, $copy->source);
        self::assertSame('ANALYZE', $copy->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeOptions;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnalyzeStatement::class)]
#[Medium]
final class AnalyzeStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ANALYZE (SKIP_LOCKED) t (a)');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertTrue($copy->options->skipLocked);
        self::assertSame(['a'], $copy->targets[0]->columns);
        self::assertSame(StatementKind::Analyze, $copy->kind);
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ANALYZE');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        self::assertSame('ANALYZE(VERBOSE)', $statement->withOptions(new AnalyzeOptions(verbose: true))->toString());
        self::assertSame('ANALYZE', $statement->toString());
    }

    public function testWithTargetsReplacesTheRelations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ANALYZE t');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        self::assertSame('ANALYZE', $statement->withTargets([])->toString());
        self::assertCount(1, $statement->targets);
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ANALYZE');
        $this->expectException(InvalidStructure::class);
        new AnalyzeStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::Sqlite));
    }
}

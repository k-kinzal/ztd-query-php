<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Maintenance\MySqlTables;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlTables::class)]
#[Medium]
final class MySqlTablesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsOrderedChecksAcrossGrammarReleases(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)');
        $binder = new Binder($schema);
        $statement = $binder->bind('CHECK TABLES t,u QUICK FAST MEDIUM EXTENDED CHANGED FOR UPGRADE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement::class, $statement);
        self::assertSame(['t', 'u'], array_map(static fn ($table): string => $table->declaration->name, $statement->tables));
        self::assertSame(['QUICK', 'FAST', 'MEDIUM', 'EXTENDED', 'CHANGED', 'FOR UPGRADE'], array_column($statement->options, 'value'));
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement::class, $rebound);
        self::assertSame($statement->options, $rebound->options);
    }

    public function testTablesRetainAnUnresolvedQualifiedTargetForDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHECKSUM TABLE app.missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\ChecksumTablesStatement::class, $statement);
        self::assertSame(['app', 'missing'], $statement->tables[0]->name->parts);
        self::assertFalse($statement->tables[0]->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
        self::assertSame(\SqlSemantics\Model\Maintenance\MySql\ChecksumMode::Automatic, $statement->mode);
    }

    #[TestWith(['check table t quick fast', 'CHECK TABLE `t` QUICK FAST'])]
    #[TestWith(['repair no_write_to_binlog table t quick extended use_frm', 'REPAIR NO_WRITE_TO_BINLOG TABLE `t` QUICK EXTENDED USE_FRM'])]
    #[TestWith(['repair table t', 'REPAIR TABLE `t`'])]
    #[TestWith(['analyze local table t', 'ANALYZE NO_WRITE_TO_BINLOG TABLE `t`'])]
    #[TestWith(['analyze table t', 'ANALYZE TABLE `t`'])]
    #[TestWith(['optimize local table t', 'OPTIMIZE NO_WRITE_TO_BINLOG TABLE `t`'])]
    #[TestWith(['optimize table t', 'OPTIMIZE TABLE `t`'])]
    #[TestWith(['checksum table t extended', 'CHECKSUM TABLE `t` EXTENDED'])]
    #[TestWith(['checksum table t quick', 'CHECKSUM TABLE `t` QUICK'])]
    #[TestWith(['analyze no_write_to_binlog table t update histogram on id', 'ANALYZE NO_WRITE_TO_BINLOG TABLE `t` UPDATE HISTOGRAM ON `id`'])]
    #[TestWith(['analyze table t drop histogram on id', 'ANALYZE TABLE `t` DROP HISTOGRAM ON `id`'])]
    public function testBindReadsEachVerbWithItsOptionsInAnyCase(string $sql, string $expected): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(id INT)');
        self::assertSame($expected, (new Binder($schema))->bind($sql)->toString());
    }

    public function testBindLeavesPostgreSqlAnalyzeToItsOwnBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('ANALYZE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeStatement::class, $statement);
    }

    public function testTablesResolvesEveryTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)');
        $node = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('OPTIMIZE TABLE t, u');
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $tables = MySqlTables::tables(new \SqlSemantics\Model\Statement\Origin('s0', $node, Dialect::MySql), $node, $context);
        self::assertSame(['t', 'u'], array_map(static fn ($table): string => $table->declaration->name, $tables));
    }
}

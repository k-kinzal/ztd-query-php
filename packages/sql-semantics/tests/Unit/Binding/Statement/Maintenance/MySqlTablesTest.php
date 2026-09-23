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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Flushes;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesForExportStatement;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesWithReadLockStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Flushes::class)]
#[Medium]
final class FlushesTest extends TestCase
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
    public function testBindDistinguishesEveryTableForm(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $plain = $binder->bind('FLUSH LOCAL TABLE t');
        self::assertInstanceOf(FlushTablesStatement::class, $plain);
        self::assertSame(BinlogPolicy::Omit, $plain->binlog);
        self::assertInstanceOf(FlushTablesWithReadLockStatement::class, $binder->bind('FLUSH TABLES WITH READ LOCK'));
        $export = $binder->bind('FLUSH TABLES t FOR EXPORT');
        self::assertInstanceOf(FlushTablesForExportStatement::class, $export);
        self::assertSame('t', $export->tables[0]->declaration->name);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($export), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($export))));
        $options = $binder->bind('FLUSH PRIVILEGES, ERROR LOGS');
        self::assertInstanceOf(FlushServerStatement::class, $options);
        self::assertSame([ServerFlush::Privileges, ServerFlush::ErrorLogs], $options->targets);
    }

    public function testTargetReadsLegacyOptionsAndRelayChannels(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("FLUSH QUERY CACHE, DES_KEY_FILE, USER_RESOURCES, RELAY LOGS FOR CHANNEL 'c'");
        self::assertInstanceOf(FlushServerStatement::class, $legacy);
        self::assertSame(ServerFlush::QueryCache, $legacy->targets[0]);
        self::assertSame(ServerFlush::UserResources, $legacy->targets[2]);
        self::assertInstanceOf(RelayLogFlush::class, $legacy->targets[3]);
        self::assertSame('c', $legacy->targets[3]->channel);
    }

    public function testTablesKeepsAnUnknownTableAsADiagnosedReference(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('FLUSH TABLES missing', strict: false);
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertFalse($statement->tables[0]->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }

    #[TestWith(['flush tables t, u with read lock', 'FLUSH TABLES `t`, `u` WITH READ LOCK'])]
    #[TestWith(['flush local logs, status', 'FLUSH NO_WRITE_TO_BINLOG LOGS, STATUS'])]
    #[TestWith(['flush tables t for export', 'FLUSH TABLES `t` FOR EXPORT'])]
    #[TestWith(['flush binary logs, engine logs', 'FLUSH BINARY LOGS, ENGINE LOGS'])]
    public function testBindReadsLowerCaseForms(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)')))->bind($sql)));
    }

    public function testTargetAndTablesReadParsedNodes(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)');
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7');
        self::assertSame(ServerFlush::Status, Flushes::target($parser->parse('flush status')->find('flush_option')[0], $context));
        $node = $parser->parse('FLUSH TABLES t, u');
        $tables = Flushes::tables(new \SqlSemantics\Model\Statement\Origin('s0', $node, Dialect::MySql), $node, $context);
        self::assertSame(['t', 'u'], array_map(static fn ($table): string => $table->declaration->name, $tables));
    }
}

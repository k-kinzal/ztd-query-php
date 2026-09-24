<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\MaintenanceBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MaintenanceBinder::class)]
#[Medium]
final class MaintenanceBinderTest extends TestCase
{
    public function testBindReadsDatabaseSelectionAndIndexRebuilding(): void
    {
        $use = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('USE db');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\UseDatabaseStatement::class, $use);
        self::assertSame(['db'], $use->database->parts);
        self::assertSame('USE `db`', $use->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexAllStatement::class, (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX'));
    }

    public function testBindSeparatesSqliteAnalyzeAndVacuumForms(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\AnalyzeAllStatement::class, $binder->bind('ANALYZE'));
        $named = $binder->bind('ANALYZE main.t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\AnalyzeNamedStatement::class, $named);
        self::assertSame(['main', 't'], $named->target->parts);
        $vacuum = $binder->bind('VACUUM');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\VacuumDatabaseStatement::class, $vacuum);
        self::assertNull($vacuum->schema);
        $into = $binder->bind("VACUUM main INTO '/tmp/x'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\VacuumIntoStatement::class, $into);
        self::assertSame('main', $into->schema);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $into->destination);
        self::assertSame("'/tmp/x'", $into->destination->text);
        self::assertSame("VACUUM \"main\" INTO '/tmp/x'", $into->toString());
    }

    public function testBindReadsDatabaseAttachmentOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $attach = $binder->bind("ATTACH DATABASE 'f.db' AS a KEY 'k'", strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\AttachDatabaseStatement::class, $attach);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $attach->database);
        self::assertSame("'f.db'", $attach->database->text);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $attach->schema);
        self::assertSame(['a'], $attach->schema->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $attach->encryptionKey);
        self::assertSame("ATTACH DATABASE 'f.db' AS \"a\" KEY 'k'", $attach->toString());
        $detach = $binder->bind('DETACH a', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\DetachDatabaseStatement::class, $detach);
        self::assertSame('DETACH DATABASE "a"', $detach->toString());
    }

    public function testBindLeavesNonSqliteAnalyzeToOtherBinders(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, $identifiers, 'public'));
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ANALYZE t');
        $origin = new \SqlSemantics\Model\Statement\Origin('s1', $source, Dialect::PostgreSql);
        self::assertNull((new MaintenanceBinder())->bind($origin, $source, new \SqlSemantics\Binding\Scope($identifiers, queries: $context)));
    }
}

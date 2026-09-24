<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\AttachDatabaseStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AttachDatabaseStatement::class)]
#[Medium]
final class AttachDatabaseStatementTest extends TestCase
{
    public function testBindsTheFileSchemaAndKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("ATTACH 'archive.db' AS archive KEY 'secret'", strict: false);
        self::assertInstanceOf(AttachDatabaseStatement::class, $statement);
        self::assertSame("'archive.db'", $statement->database->spelling());
        self::assertSame('archive', $statement->schema->spelling());
        self::assertSame("'secret'", $statement->encryptionKey?->spelling());
        self::assertSame(StatementKind::Attach, $statement->kind);
        self::assertSame('ATTACH DATABASE \'archive.db\' AS "archive" KEY \'secret\'', $statement->toString());
    }

    public function testWithOriginPreservesEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("ATTACH DATABASE 'archive.db' AS archive", strict: false);
        self::assertInstanceOf(AttachDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame([], $copy->diagnostics);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->schema, $copy->schema);
        self::assertNull($copy->encryptionKey);
        self::assertSame($statement->toString(), $copy->toString());
    }
}

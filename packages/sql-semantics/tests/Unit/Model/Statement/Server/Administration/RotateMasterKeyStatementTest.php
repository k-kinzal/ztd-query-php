<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\MasterKeyScope;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration\RotateMasterKeyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RotateMasterKeyStatement::class)]
#[Medium]
final class RotateMasterKeyStatementTest extends TestCase
{
    public function testWithOriginPreservesTheScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ROTATE innodb MASTER KEY');
        self::assertInstanceOf(RotateMasterKeyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(MasterKeyScope::InnoDb, $copy->scope);
        self::assertSame('ALTER INSTANCE ROTATE INNODB MASTER KEY', $copy->toString());
    }

    public function testWithScopeSelectsTheOtherKeyImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        self::assertInstanceOf(RotateMasterKeyStatement::class, $statement);
        self::assertSame('ALTER INSTANCE ROTATE BINLOG MASTER KEY', $statement->withScope(MasterKeyScope::BinaryLog)->toString());
        self::assertSame(MasterKeyScope::InnoDb, $statement->scope);
    }

    public function testRejectsBinaryLogKeysBeforeMySql8(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        new RotateMasterKeyStatement($statement->origin, MasterKeyScope::BinaryLog);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        new RotateMasterKeyStatement(new Origin('s0', $statement->source, Dialect::Sqlite), MasterKeyScope::InnoDb);
    }

    public function testNamesTheUnavailableKeyInTheViolation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('ALTER INSTANCE ROTATE BINLOG MASTER KEY is not available in this MySQL release.');
        new RotateMasterKeyStatement($statement->origin, MasterKeyScope::BinaryLog);
    }

    public function testAcceptsBinaryLogKeysFromMySql8(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('ALTER INSTANCE ROTATE BINLOG MASTER KEY');
        self::assertInstanceOf(RotateMasterKeyStatement::class, $statement);
        self::assertSame(MasterKeyScope::BinaryLog, $statement->scope);
    }
}

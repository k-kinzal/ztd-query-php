<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Administration\AlterRedoLogStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRedoLogStatement::class)]
#[Medium]
final class AlterRedoLogStatementTest extends TestCase
{
    public function testWithOriginPreservesTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ENABLE InnoDB Redo_Log');
        self::assertInstanceOf(AlterRedoLogStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertTrue($copy->enabled);
        self::assertSame('ALTER INSTANCE ENABLE INNODB REDO_LOG', $copy->toString());
    }

    public function testWithEnabledChangesTheRequestImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ENABLE INNODB REDO_LOG');
        self::assertInstanceOf(AlterRedoLogStatement::class, $statement);
        self::assertSame('ALTER INSTANCE DISABLE INNODB REDO_LOG', $statement->withEnabled(false)->toString());
        self::assertTrue($statement->enabled);
    }

    public function testRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        new AlterRedoLogStatement($statement->origin, true);
    }
}

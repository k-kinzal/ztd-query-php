<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\AlterTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTablespaceStatement::class)]
#[Medium]
final class AlterTablespaceStatementTest extends TestCase
{
    public function testWithOriginRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE ts NO_WAIT');
        self::assertInstanceOf(AlterTablespaceStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE `ts` NO_WAIT', $statement->withOrigin($statement->origin)->toString());
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($legacy);
    }

    public function testWithNameKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE ts WAIT');
        self::assertInstanceOf(AlterTablespaceStatement::class, $statement);
        self::assertSame('t`2', $statement->withName('t`2')->name);
        self::assertSame('ts', $statement->name);
    }

    public function testWithChangesReplacesTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE ts AUTOEXTEND_SIZE 4M');
        self::assertInstanceOf(AlterTablespaceStatement::class, $statement);
        $changed = $statement->withChanges(new TablespaceChanges(engineAttribute: '{"k": 1}', waiting: CompletionWait::NoWait));
        self::assertSame(4194304, $statement->changes->autoextendSize);
        self::assertSame('ALTER TABLESPACE `ts` ENGINE_ATTRIBUTE = \'{"k": 1}\' NO_WAIT', $changed->toString());
    }
}

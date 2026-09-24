<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\ProceduralCommands;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProceduralCommands::class)]
#[Medium]
final class ProceduralCommandsTest extends TestCase
{
    public function testBindRoutesEachProceduralForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        self::assertSame('HELP', $binder->bind("HELP 'x'")->kind->value);
        self::assertSame('IMPORT', $binder->bind("IMPORT TABLE FROM 'x'")->kind->value);
        self::assertSame('LOCK', $binder->bind('LOCK INSTANCE FOR BACKUP')->kind->value);
    }

    public function testBindLeavesOtherDialectsToTheirBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Locking\LockRelationsStatement::class, $statement);
    }
}

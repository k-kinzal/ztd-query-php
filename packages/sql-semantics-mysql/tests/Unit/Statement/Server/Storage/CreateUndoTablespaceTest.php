<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace;

#[CoversClass(CreateUndoTablespace::class)]
#[Medium]
final class CreateUndoTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE innodb", (new Semantics(Dialect::MySql))->analyze("create undo tablespace u add datafile 'u.ibu' engine innodb")->toString());
    }

    public function testDeriveStatementAcceptsAConsistentOptionList(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE a");

        self::assertSame([], $operation->diagnostics());
    }

    public function testValueRejectsAnUnquotedDatafile(): void
    {
        $this->expectExceptionMessage('A tablespace file name is a quoted string.');
        new CreateUndoTablespace(new \SqlSemantics\Statement\Identifier\Name('u'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('0', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit));
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace;

#[CoversClass(DropUndoTablespace::class)]
#[Medium]
final class DropUndoTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('DROP UNDO TABLESPACE u ENGINE innodb', (new Semantics())->diagnose('drop undo tablespace u engine innodb')->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics())->diagnose('DROP UNDO TABLESPACE u ENGINE a ENGINE b');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;

#[CoversClass(DropTablespace::class)]
#[Medium]
final class DropTablespaceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('DROP TABLESPACE ts', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('drop tablespace ts')->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP TABLESPACE ts ENGINE a ENGINE b');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

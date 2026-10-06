<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;

#[CoversClass(DropLogfileGroup::class)]
#[Medium]
final class DropLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('DROP LOGFILE GROUP g', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('drop logfile group g')->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP LOGFILE GROUP g ENGINE a ENGINE b');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

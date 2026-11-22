<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropLogfileGroup::class)]
#[Medium]
final class DropLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('DROP LOGFILE GROUP g', (new Inspection('mysql-5.7.44'))->diagnose('drop logfile group g')->toString());
    }

    public function testDeriveStatementReportsRejectedOptions(): void
    {
        $operation = (new Inspection())->diagnose('DROP LOGFILE GROUP g ENGINE a ENGINE b');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }
}

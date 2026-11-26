<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RenameTo::class)]
#[Medium]
final class RenameToTest extends TestCase
{
    public function testDeriveCommandReportsANameAnotherTableHas(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT, b INT)');
        $other = $semantics->diagnose('CREATE TABLE u (a INT)');

        self::assertSame('Table u already exists.', $semantics->diagnose('ALTER TABLE t RENAME u', [$table, $other])->facts->diagnostics[0]->message());
        self::assertSame([], $semantics->diagnose('ALTER TABLE t RENAME TO t', [$table, $other])->facts->diagnostics);
    }

    public function testRenderWritesTo(): void
    {
        self::assertSame('ALTER TABLE t RENAME TO db.u', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t RENAME = db.u')->toString());
    }
}

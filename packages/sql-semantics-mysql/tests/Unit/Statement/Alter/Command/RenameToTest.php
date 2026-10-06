<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;

#[CoversClass(RenameTo::class)]
#[Medium]
final class RenameToTest extends TestCase
{
    public function testDeriveCommandReportsANameAnotherTableHas(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $other = $semantics->analyze('CREATE TABLE u (a INT)');

        self::assertSame('Table u already exists.', $semantics->analyze('ALTER TABLE t RENAME u', [$table, $other])->facts->diagnostics[0]->message());
        self::assertSame([], $semantics->analyze('ALTER TABLE t RENAME TO t', [$table, $other])->facts->diagnostics);
    }

    public function testRenderWritesTo(): void
    {
        self::assertSame('ALTER TABLE t RENAME TO db.u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t RENAME = db.u')->toString());
    }
}

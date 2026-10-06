<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;

#[CoversClass(ConvertCharset::class)]
#[Medium]
final class ConvertCharsetTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t CONVERT TO CHARACTER SET utf8mb4', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheCharsetAndTheCollation(): void
    {
        self::assertSame('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT COLLATE latin1_bin', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t CONVERT TO CHAR SET DEFAULT COLLATE latin1_bin')->toString());
    }
}

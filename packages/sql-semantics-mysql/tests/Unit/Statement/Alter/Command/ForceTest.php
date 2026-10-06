<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Force;

#[CoversClass(Force::class)]
#[Medium]
final class ForceTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t FORCE', [$table])->facts->diagnostics);
    }

    public function testRenderWritesForce(): void
    {
        self::assertSame('ALTER TABLE t FORCE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t FORCE')->toString());
    }
}

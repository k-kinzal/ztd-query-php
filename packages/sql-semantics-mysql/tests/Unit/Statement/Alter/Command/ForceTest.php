<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Force;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Force::class)]
#[Medium]
final class ForceTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->inspect('ALTER TABLE t FORCE', [$table])->facts->diagnostics);
    }

    public function testRenderWritesForce(): void
    {
        self::assertSame('ALTER TABLE t FORCE', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t FORCE')->toString());
    }
}

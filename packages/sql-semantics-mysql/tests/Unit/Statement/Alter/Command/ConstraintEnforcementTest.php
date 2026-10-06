<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement;

#[CoversClass(ConstraintEnforcement::class)]
#[Medium]
final class ConstraintEnforcementTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t ALTER CONSTRAINT c ENFORCED', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheEnforcement(): void
    {
        self::assertSame('ALTER TABLE t ALTER CONSTRAINT c ENFORCED', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALTER CONSTRAINT c ENFORCED')->toString());
    }
}

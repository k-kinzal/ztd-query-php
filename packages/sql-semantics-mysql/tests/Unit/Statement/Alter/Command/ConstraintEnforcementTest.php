<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ConstraintEnforcement::class)]
#[Medium]
final class ConstraintEnforcementTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->inspect('ALTER TABLE t ALTER CONSTRAINT c ENFORCED', [$table])->facts->diagnostics);
    }

    public function testRenderWritesTheEnforcement(): void
    {
        self::assertSame('ALTER TABLE t ALTER CONSTRAINT c ENFORCED', (new Inspection())->inspect('ALTER TABLE t ALTER CONSTRAINT c ENFORCED')->toString());
    }
}

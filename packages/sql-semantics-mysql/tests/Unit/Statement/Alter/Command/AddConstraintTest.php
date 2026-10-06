<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;

#[CoversClass(AddConstraint::class)]
#[Medium]
final class AddConstraintTest extends TestCase
{
    public function testDeriveCommandDerivesTheElementInTheChangedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ADD CHECK (a > x)', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheElement(): void
    {
        self::assertSame('ALTER TABLE t ADD UNIQUE INDEX u (a)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD UNIQUE KEY u (a)')->toString());
    }
}

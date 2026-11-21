<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;

#[CoversClass(KeyMethod::class)]
#[Medium]
final class KeyMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');

        self::assertSame(['Column q does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t PARTITION BY KEY (a, q)', [$table])->facts->diagnostics));
    }

    public function testRenderWritesLinearAndTheAlgorithm(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LINEAR KEY ALGORITHM = 2 ()', (new Semantics())->diagnose('alter table t partition by linear key algorithm=2 ()')->toString());
    }
}

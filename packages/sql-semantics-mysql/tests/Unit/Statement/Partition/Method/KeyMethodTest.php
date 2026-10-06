<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(KeyMethod::class)]
#[Medium]
final class KeyMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame(['Column q does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t PARTITION BY KEY (a, q)', [$table])->facts->diagnostics));
    }

    public function testRenderWritesLinearAndTheAlgorithm(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LINEAR KEY ALGORITHM = 2 ()', (new Semantics(Dialect::MySql))->analyze('alter table t partition by linear key algorithm=2 ()')->toString());
    }
}

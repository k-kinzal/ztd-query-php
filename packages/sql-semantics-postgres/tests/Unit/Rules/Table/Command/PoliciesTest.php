<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Policies::class)]
#[Medium]
final class PoliciesTest extends TestCase
{
    public function testDeriveDerivesTheConditions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE POLICY p ON t USING (zz) WITH CHECK (a + 1)', $context);
        self::assertSame([
          0 => 'Column zz does not exist.',
          1 => 'argument of POLICY must be type boolean',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteWritesTheRolesAndConditions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE POLICY p ON t TO bob USING (true) WITH CHECK (false)', []);
        self::assertSame('CREATE POLICY p ON t TO bob USING (TRUE) WITH CHECK (FALSE)', $statement->toString());
    }

    public function testDeriveReportsAView(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE POLICY p ON v', $context)->facts->diagnostics));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AlterIdentity::class)]
#[Medium]
final class AlterIdentityTest extends TestCase
{
    public function testDeriveClauseChecksTheColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE t ALTER zz RESTART', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ALTER a SET GENERATED ALWAYS RESTART WITH 3 SET NO CYCLE', []);
        self::assertSame('ALTER TABLE t ALTER a SET GENERATED ALWAYS RESTART 3 SET NO CYCLE', $statement->toString());
    }

    public function testRefusesAnEmptyChangeList(): void
    {
        $this->expectExceptionMessage('An identity change list holds at least one change.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AlterIdentity(new \SqlSemantics\Statement\Identifier\Name('a'), []);
    }
}

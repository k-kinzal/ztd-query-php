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
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER TABLE t ALTER zz RESTART', $context);
        self::assertSame([
          0 => 'column "zz" does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER TABLE t ALTER a SET GENERATED ALWAYS RESTART WITH 3 SET NO CYCLE', []);
        self::assertSame('ALTER TABLE t ALTER a SET GENERATED ALWAYS RESTART 3 SET NO CYCLE', $statement->toString());
    }

    public function testRefusesAnEmptyChangeList(): void
    {
        $this->expectExceptionMessage('An identity change list holds at least one change.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AlterIdentity(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), []);
    }
}

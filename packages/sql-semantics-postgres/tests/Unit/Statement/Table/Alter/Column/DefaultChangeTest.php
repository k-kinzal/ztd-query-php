<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DefaultChange::class)]
#[Medium]
final class DefaultChangeTest extends TestCase
{
    public function testDeriveClauseSeesNoColumn(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER TABLE t ALTER b SET DEFAULT a', $context);
        self::assertSame([
          0 => 'Column a does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER TABLE t ALTER b SET DEFAULT 1 + 2', []);
        self::assertSame('ALTER TABLE t ALTER b SET DEFAULT 1 + 2', $statement->toString());
    }
}

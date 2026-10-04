<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions::class)]
#[Medium]
final class ColumnOptionsTest extends TestCase
{
    public function testDeriveClauseReportsTheProblemsOfTheQualifiers(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p (a NULL NOT NULL) DEFAULT', []);
        self::assertSame([
          0 => 'Relation p does not exist.',
          1 => 'conflicting NULL/NOT NULL declarations for column "a"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t OF ty (a WITH OPTIONS DEFAULT 1, b NOT NULL)', []);
        self::assertSame('CREATE TABLE t OF ty (a DEFAULT 1, b NOT NULL)', $statement->toString());
    }
}

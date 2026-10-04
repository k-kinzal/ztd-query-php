<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ExclusionElement::class)]
#[Medium]
final class ExclusionElementTest extends TestCase
{
    public function testDeriveClauseResolvesTheKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, EXCLUDE (zz WITH =))', []);
        self::assertSame([
          0 => 'Column zz does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, EXCLUDE (a DESC WITH &&))', []);
        self::assertSame('CREATE TABLE t (a INT, EXCLUDE (a DESC WITH &&))', $statement->toString());
    }
}

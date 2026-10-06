<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence::class)]
#[Medium]
final class PersistenceTest extends TestCase
{
    public function testTemporaryIsTrueForTheTemporarySpellings(): void
    {
        self::assertSame([
          0 => false,
          1 => true,
          2 => true,
          3 => true,
          4 => true,
          5 => true,
          6 => true,
          7 => false,
        ], array_map(static fn ($persistence): bool => $persistence->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence::cases()));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE GLOBAL TEMPORARY TABLE t (a int)', []);
        self::assertSame('CREATE GLOBAL TEMPORARY TABLE t (a INT)', $statement->toString());
    }
}

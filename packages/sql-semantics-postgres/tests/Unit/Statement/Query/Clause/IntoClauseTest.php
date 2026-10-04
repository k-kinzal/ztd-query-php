<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoClause::class)]
#[Medium]
final class IntoClauseTest extends TestCase
{
    public function testRenderWritesThePersistenceAndTheName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a INTO GLOBAL TEMPORARY TABLE s.n');
        self::assertSame('SELECT 1 AS a INTO GLOBAL TEMPORARY s.n', $query->toString());
    }
}

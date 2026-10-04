<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow::class)]
#[Medium]
final class ValuesRowTest extends TestCase
{
    public function testRenderWritesTheValuesInParentheses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("VALUES (1, 'a', NULL)");
        self::assertSame("VALUES (1, 'a', NULL)", $query->toString());
    }
}

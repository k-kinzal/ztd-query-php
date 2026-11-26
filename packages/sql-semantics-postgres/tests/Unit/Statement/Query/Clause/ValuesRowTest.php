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
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect("VALUES (1, 'a', NULL)");
        self::assertSame("VALUES (1, 'a', NULL)", $query->toString());
    }
}

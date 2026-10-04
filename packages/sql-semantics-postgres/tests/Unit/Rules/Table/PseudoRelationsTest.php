<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\PseudoRelations::class)]
#[Medium]
final class PseudoRelationsTest extends TestCase
{
    public function testScopeHoldsOldAndNew(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER UPDATE ON t FOR EACH ROW WHEN (old.a = new.a AND a > 0) EXECUTE FUNCTION f()', $context);
        self::assertSame([
          0 => 'Column a is ambiguous.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ForeignOptions::class)]
#[Medium]
final class ForeignOptionsTest extends TestCase
{
    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER FOREIGN TABLE f OPTIONS (ADD x \'y\')', []);
        self::assertSame([
          0 => 'Relation f does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER FOREIGN TABLE f OPTIONS (x \'y\', DROP z)', []);
        self::assertSame('ALTER FOREIGN TABLE f OPTIONS (ADD x \'y\', DROP z)', $statement->toString());
    }
}

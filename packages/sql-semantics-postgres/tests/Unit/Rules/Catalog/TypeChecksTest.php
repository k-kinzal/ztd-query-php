<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\TypeChecks::class)]
#[Medium]
final class TypeChecksTest extends TestCase
{
    public function testLabelsAcceptsSixtyThreeBytes(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE e ADD VALUE \'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz\'')->facts->diagnostics);
    }

    public function testRangeReportsAnUnknownAttribute(): void
    {
        self::assertSame('type attribute "foo" not recognized', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype = int4, foo = 1)')->facts->diagnostics[0]->message());
    }

    public function testRangeReportsARepeatedAttribute(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype = int4, subtype = int8)')->facts->diagnostics[0]->message());
    }

    public function testAttributesReportsEachRepeatedNameOnce(): void
    {
        self::assertCount(1, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE pair AS (a int4, a int4, a int4)')->facts->diagnostics);
    }
}

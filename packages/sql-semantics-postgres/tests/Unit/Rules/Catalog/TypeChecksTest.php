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

    public function testAttributesReportsEachRepeatedNameOnce(): void
    {
        self::assertCount(1, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE pair AS (a int4, a int4, a int4)')->facts->diagnostics);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;

#[CoversClass(DeferredProjection::class)]
#[Medium]
final class DeferredProjectionTest extends TestCase
{
    public function testRenderKeepsTheProjectionAndAlias(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT (1, 2) AS result', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT (1,2) AS result')->toString());
    }
}

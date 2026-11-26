<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowWhere::class)]
#[Medium]
final class ShowWhereTest extends TestCase
{
    public function testRenderWritesTheCondition(): void
    {
        self::assertSame('SHOW STATUS WHERE Variable_name = 1', (new Inspection())->inspect('SHOW STATUS WHERE Variable_name = 1')->toString());
    }
}

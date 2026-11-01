<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;

#[CoversClass(ShowLike::class)]
#[Medium]
final class ShowLikeTest extends TestCase
{
    public function testRenderWritesThePattern(): void
    {
        self::assertSame("SHOW COLLATION LIKE 'a\\\\%'", (new Semantics(Dialect::MySql))->analyze("SHOW COLLATION LIKE 'a\\\\%'")->toString());
    }

    public function testValueRejectsIncompatiblePattern(): void
    {
        $this->expectExceptionMessage('A SHOW LIKE pattern is quoted text.');
        new ShowLike(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}

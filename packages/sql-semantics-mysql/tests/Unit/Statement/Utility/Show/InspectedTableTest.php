<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;

#[CoversClass(InspectedTable::class)]
#[Medium]
final class InspectedTableTest extends TestCase
{
    public function testRenderWritesTheDatabase(): void
    {
        self::assertSame('SHOW CREATE TABLE `my db`.t', (new Semantics(Dialect::MySql))->analyze('show create table `my db`.t')->toString());
    }
}

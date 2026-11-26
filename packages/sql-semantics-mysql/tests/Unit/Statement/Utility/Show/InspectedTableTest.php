<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InspectedTable::class)]
#[Medium]
final class InspectedTableTest extends TestCase
{
    public function testRenderWritesTheDatabase(): void
    {
        self::assertSame('SHOW CREATE TABLE `my db`.t', (new Inspection())->inspect('show create table `my db`.t')->toString());
    }
}

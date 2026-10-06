<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\Spine;

#[CoversClass(Spine::class)]
#[Medium]
final class SpineTest extends TestCase
{
    public function testItemsAnswersTheItemsInOrder(): void
    {
        self::assertSame('RESET MASTER, SLAVE, QUERY CACHE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('reset master, slave, query cache')->toString());
    }
}

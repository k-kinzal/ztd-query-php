<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;

#[CoversClass(Reach::class)]
#[Small]
final class ReachTest extends TestCase
{
    public function testSessionHoldsForSessionAndBoth(): void
    {
        self::assertSame([false, true, true], [Reach::Global->session(), Reach::Session->session(), Reach::Both->session()]);
    }

    public function testGlobalHoldsForGlobalAndBoth(): void
    {
        self::assertSame([true, false, true], [Reach::Global->global(), Reach::Session->global(), Reach::Both->global()]);
    }
}

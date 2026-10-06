<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption;

#[CoversClass(WaitOption::class)]
#[Medium]
final class WaitOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame('DROP LOGFILE GROUP g WAIT', (new Semantics(Dialect::MySql))->analyze('drop logfile group g wait')->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('WAIT', (new WaitOption(true))->keyword());
    }
}

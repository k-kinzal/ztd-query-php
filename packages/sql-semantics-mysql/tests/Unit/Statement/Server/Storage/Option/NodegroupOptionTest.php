<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;

#[CoversClass(NodegroupOption::class)]
#[Medium]
final class NodegroupOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'f' NODEGROUP 3", (new Semantics(Dialect::MySql))->analyze("create logfile group g add undofile 'f' nodegroup = 3")->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('NODEGROUP', (new NodegroupOption(new Numeral('3')))->keyword());
    }
}

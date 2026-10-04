<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite;

#[CoversClass(DatabaseRewrite::class)]
#[Medium]
final class DatabaseRewriteTest extends TestCase
{
    public function testRenderWritesThePair(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, `b c`))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('change replication filter replicate_rewrite_db = ((a, `b c`))')->toString());
    }
}

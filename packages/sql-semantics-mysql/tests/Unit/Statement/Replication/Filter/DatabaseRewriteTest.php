<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DatabaseRewrite::class)]
#[Medium]
final class DatabaseRewriteTest extends TestCase
{
    public function testRenderWritesThePair(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, `b c`))', (new Inspection('mysql-5.7.44'))->inspect('change replication filter replicate_rewrite_db = ((a, `b c`))')->toString());
    }
}

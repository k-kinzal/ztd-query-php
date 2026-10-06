<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ReplicationFilter::class)]
#[Medium]
final class ReplicationFilterTest extends TestCase
{
    public function testRenderWritesTablesAndTheEmptyList(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (a.b, c.d), REPLICATE_IGNORE_DB = ()', (new Semantics(Dialect::MySql))->analyze('change replication filter replicate_do_table = (a.b, c.d), replicate_ignore_db = ()')->toString());
    }

    public function testValuesRejectAnUnqualifiedTable(): void
    {
        $this->expectExceptionMessage('A filtered table is qualified by its database.');

        new ReplicationFilter(FilterKind::DoTable, [new QualifiedName(new Name('t'))]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Projection\TableFields;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableFields::class)]
#[Small]
final class TableFieldsTest extends TestCase
{
    public function testApplyUsesTheSemanticExistenceProjectionAcrossSetBranches(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT, b INT); INSERT INTO t VALUES(NULL,NULL)');
        $result = $session->query('SELECT EXISTS(TABLE t UNION SELECT 1) AS yes, EXISTS(TABLE t LIMIT 0) AS no')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
        self::assertSame([1, 1], [$result->columns[0]->flags & 1, $result->columns[1]->flags & 1]);
        $ordinary = $session->query('TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $ordinary);
        self::assertSame([[null, null]], $ordinary->rows);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ProcessQueryText;
use SqlSemantics\Model\Statement\Inspection\ShowProcessesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcessQueryText::class)]
#[Medium]
final class ProcessQueryTextTest extends TestCase
{
    #[TestWith(['SHOW PROCESSLIST', ProcessQueryText::Preview])]
    #[TestWith(['SHOW FULL PROCESSLIST', ProcessQueryText::Complete])]
    public function testRetainsTheRequestedQueryTextLength(string $sql, ProcessQueryText $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        self::assertSame($expected, $statement->queryText);
    }

}

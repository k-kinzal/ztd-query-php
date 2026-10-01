<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\DeleteReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

#[CoversClass(DeleteReader::class)]
#[Medium]
final class DeleteReaderTest extends TestCase
{
    #[TestWith([MySql::MySql, 'DELETE FROM bar LIMIT 1'])]
    #[TestWith([PostgreSql::PostgreSql, 'DELETE FROM bar USING other WHERE bar.foo = other.foo'])]
    #[TestWith([PostgreSql::PostgreSql, 'DELETE FROM ONLY bar'])]
    #[TestWith([Sqlite::Sqlite, 'DELETE FROM bar RETURNING foo'])]
    public function testReadRejectsAdditionalOperationsWithoutDroppingThem(Dialect $dialect, string $sql): void
    {
        $this->expectException(SemanticException::class);
        (new Semantics($dialect))->analyze($sql);
    }
}

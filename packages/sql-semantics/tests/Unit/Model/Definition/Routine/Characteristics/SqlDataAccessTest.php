<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Characteristics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqlDataAccess::class)]
#[Medium]
final class SqlDataAccessTest extends TestCase
{
    /**
     * @param non-empty-string $sql A complete data-access characteristic
     */
    #[TestWith(['NO SQL', SqlDataAccess::None])]
    #[TestWith(['CONTAINS SQL', SqlDataAccess::Contains])]
    #[TestWith(['READS SQL DATA', SqlDataAccess::Reads])]
    #[TestWith(['MODIFIES SQL DATA', SqlDataAccess::Modifies])]
    public function testDeclarationsIdentifyDifferentDataAccessContracts(string $sql, SqlDataAccess $access): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION f ' . $sql);
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        self::assertSame($access, $statement->changes->dataAccess);
        self::assertStringEndsWith($sql, $statement->toString());
    }
}

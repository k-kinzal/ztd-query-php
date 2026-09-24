<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableAlgorithm::class)]
#[Medium]
final class TableAlgorithmTest extends TestCase
{
    #[TestWith(['DEFAULT', 'Default'])]
    #[TestWith(['INSTANT', 'Instant'])]
    #[TestWith(['INPLACE', 'Inplace'])]
    #[TestWith(['COPY', 'Copy'])]
    public function testIsReadFromTheStatement(string $algorithm, string $case): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t ALGORITHM = ' . $algorithm . ', FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame($case, $statement->algorithm->name);
    }
}

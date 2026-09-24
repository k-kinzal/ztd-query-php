<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\PartitionProcess;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\ProcessPartitions;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionProcess::class)]
#[Medium]
final class PartitionProcessTest extends TestCase
{
    #[TestWith(['REBUILD'])]
    #[TestWith(['OPTIMIZE'])]
    #[TestWith(['ANALYZE'])]
    public function testIsReadFromTheCommand(string $process): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t ' . $process . ' PARTITION ALL');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ProcessPartitions::class, $alteration);
        self::assertSame($process, $alteration->process->value);
    }
}

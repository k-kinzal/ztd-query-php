<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Statement\Definition\DropTableIndexStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IndexAlgorithm::class)]
#[Medium]
final class IndexAlgorithmTest extends TestCase
{
    public function testRepresentsEveryMySqlIndexAlgorithm(): void
    {
        self::assertSame(['DEFAULT', 'INPLACE', 'COPY'], array_column(IndexAlgorithm::cases(), 'value'));
    }

    #[TestWith(['DROP INDEX ix ON t ALGORITHM=INPLACE', IndexAlgorithm::Inplace, 'DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = DEFAULT'])]
    #[TestWith(['DROP INDEX ix ON t ALGORITHM=COPY', IndexAlgorithm::Copy, 'DROP INDEX `ix` ON `t` ALGORITHM = COPY LOCK = DEFAULT'])]
    #[TestWith(['DROP INDEX ix ON t', IndexAlgorithm::Default, 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = DEFAULT'])]
    public function testBindsTheRequestedAlgorithmAndWritesItBack(string $sql, IndexAlgorithm $algorithm, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(DropTableIndexStatement::class, $statement);
        self::assertSame($algorithm, $statement->algorithm);
        self::assertSame($expected, $statement->toString());
    }
}

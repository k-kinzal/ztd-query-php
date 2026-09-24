<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\AlterPolicies;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterPolicies::class)]
#[Medium]
final class AlterPoliciesTest extends TestCase
{
    public function testAlgorithmRejectsAnUnknownAlgorithm(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AlterAlgorithm->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALGORITHM = FAST');
    }

    public function testLockRejectsAnUnknownLock(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AlterLock->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t LOCK = PARTIAL');
    }

    public function testIndexAlgorithmRejectsInstant(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AlterAlgorithm->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('CREATE INDEX ix ON t (id) ALGORITHM = INSTANT');
    }

    public function testLastReturnsTheLastRequest(): void
    {
        $statement = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ALGORITHM = COPY, ALGORITHM = INPLACE');
        $option = AlterPolicies::last($statement, 'alter_algorithm_option');
        self::assertNotNull($option);
        self::assertSame('INPLACE', AlterPolicies::word($option, new Identifiers(Dialect::MySql)));
        self::assertSame(TableAlgorithm::Inplace, AlterPolicies::algorithm($statement, new Identifiers(Dialect::MySql)));
    }

    public function testWordReadsQuotedValues(): void
    {
        $statement = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t LOCK = `none`');
        self::assertSame(IndexLock::None, AlterPolicies::lock($statement, new Identifiers(Dialect::MySql)));
        self::assertSame(IndexAlgorithm::Default, AlterPolicies::indexAlgorithm($statement, new Identifiers(Dialect::MySql)));
    }
}

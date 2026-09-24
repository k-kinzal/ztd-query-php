<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\InsertPolicy;
use SqlSemantics\Model\Write\Policy\MySqlInsertion;
use SqlSemantics\Model\Write\Policy\PostgreSqlInsertion;
use SqlSemantics\Model\Write\Policy\SqliteInsertion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertPolicy::class)]
#[Medium]
final class InsertPolicyTest extends TestCase
{
    #[TestWith([Dialect::MySql, MySqlInsertion::class])]
    #[TestWith([Dialect::PostgreSql, PostgreSqlInsertion::class])]
    #[TestWith([Dialect::Sqlite, SqliteInsertion::class])]
    public function testDialectIdentifiesTheLanguageOfTheBoundPolicy(Dialect $dialect, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t VALUES(1)');
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertSame($class, $statement->policy::class);
        self::assertSame($dialect, $statement->policy->dialect());
    }

    public function testDialectOfEachPolicyIsFixedByItsClass(): void
    {
        self::assertSame(Dialect::MySql, (new MySqlInsertion())->dialect());
        self::assertSame(Dialect::PostgreSql, (new PostgreSqlInsertion())->dialect());
        self::assertSame(Dialect::Sqlite, (new SqliteInsertion())->dialect());
    }
}

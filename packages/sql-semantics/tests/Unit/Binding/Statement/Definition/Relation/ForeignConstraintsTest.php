<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\ForeignConstraints;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ForeignConstraints::class)]
#[Medium]
final class ForeignConstraintsTest extends TestCase
{
    #[TestWith(['CREATE FOREIGN TABLE f (CONSTRAINT c UNIQUE USING INDEX i) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f (a integer PRIMARY KEY) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (EXCLUDE (id WITH =)) DEFAULT SERVER s'])]
    #[TestWith(['ALTER FOREIGN TABLE t ADD COLUMN b integer REFERENCES t'])]
    #[TestWith(['ALTER FOREIGN TABLE t ADD FOREIGN KEY (id) REFERENCES t'])]
    public function testCheckRejectsKeysForeignKeysAndExclusions(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ForeignTableConstraint->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['CREATE FOREIGN TABLE f (a integer NOT NULL CHECK (a > 0), CHECK (a < 9)) SERVER s', 'CREATE FOREIGN TABLE "public"."f"("a" integer NOT NULL, CHECK (("a" > 0)), CHECK (("a" < 9))) SERVER "s"'])]
    #[TestWith(['ALTER FOREIGN TABLE t ADD CHECK (id > 0)', 'ALTER FOREIGN TABLE "t" ADD CHECK (("id" > 0))'])]
    #[TestWith(['ALTER TABLE t ADD UNIQUE (id)', 'ALTER TABLE "t" ADD UNIQUE("id")'])]
    public function testCheckAcceptsNotNullAndCheckConstraints(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql, strict: false)));
    }
}

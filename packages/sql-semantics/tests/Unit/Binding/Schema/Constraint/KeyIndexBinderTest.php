<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Schema\Constraint\KeyIndexBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KeyIndexBinder::class)]
#[Medium]
final class KeyIndexBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsMySqlIndexOptionsOnEveryRelease(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        $statement = $binder->bind("CREATE TABLE u (a INT, b INT, CONSTRAINT pk PRIMARY KEY USING BTREE (a) COMMENT 'c' KEY_BLOCK_SIZE = 8, CONSTRAINT c UNIQUE KEY uk (b), UNIQUE INDEX u2 TYPE HASH (a, b))");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $expected = "CREATE TABLE `u`(`a` integer NOT NULL, `b` integer, CONSTRAINT `pk` PRIMARY KEY USING BTREE(`a`) KEY_BLOCK_SIZE = 8 COMMENT 'c', CONSTRAINT `c` UNIQUE KEY `uk`(`b`), UNIQUE KEY `u2` USING HASH(`a`, `b`))";
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindKeepsPostgreSqlColumnKeyOptions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)'));
        $statement = $binder->bind('ALTER TABLE p ADD COLUMN z INT UNIQUE NULLS NOT DISTINCT WITH (fillfactor = 70) USING INDEX TABLESPACE ts');
        $expected = 'ALTER TABLE "p" ADD COLUMN "z" integer UNIQUE NULLS NOT DISTINCT WITH ("fillfactor" = 70) USING INDEX TABLESPACE "ts"';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['CREATE TABLE u (a INT, UNIQUE KEY uk (a))', 'uk'])]
    #[TestWith(['CREATE TABLE u (a INT, UNIQUE USING BTREE (a))', null])]
    #[TestWith(['CREATE TABLE u (a INT, FOREIGN KEY fk (a) REFERENCES p (x))', 'fk'])]
    public function testNameReadsTheWrittenIndexName(string $sql, ?string $name): void
    {
        $tree = (new DialectParser(Dialect::MySql))->parse($sql);
        $constraint = $tree->find('table_constraint_def')[0];
        self::assertSame($name, KeyIndexBinder::name($constraint, new Scope(new Identifiers(Dialect::MySql))));
    }
}

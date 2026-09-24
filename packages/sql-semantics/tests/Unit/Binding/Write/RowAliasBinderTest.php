<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Write\RowAliasBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Write\Policy\MySqlInsertion;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(RowAliasBinder::class)]
#[Medium]
final class RowAliasBinderTest extends TestCase
{
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n ON DUPLICATE KEY UPDATE a = n.a', 'INSERT INTO `t`(`id`, `a`) VALUES (1, 1) AS `n` ON DUPLICATE KEY UPDATE `a` = `n`.`a`'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n(x, y) ON DUPLICATE KEY UPDATE a = y', 'INSERT INTO `t`(`id`, `a`) VALUES (1, 1) AS `n`(`x`, `y`) ON DUPLICATE KEY UPDATE `a` = `y`'])]
    #[TestWith(['INSERT INTO t VALUES (1, 1, 1) AS n(x, y, z) ON DUPLICATE KEY UPDATE a = n.z', 'INSERT INTO `t` VALUES (1, 1, 1) AS `n`(`x`, `y`, `z`) ON DUPLICATE KEY UPDATE `a` = `n`.`z`'])]
    #[TestWith(['INSERT INTO t SET id = 1, a = 2 AS n ON DUPLICATE KEY UPDATE b = n.a', 'INSERT INTO `t` SET `id` = 1, `a` = 2 AS `n` ON DUPLICATE KEY UPDATE `b` = `n`.`a`'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (2, 1) AS n', 'INSERT INTO `t`(`id`, `a`) VALUES (2, 1) AS `n`'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n ON DUPLICATE KEY UPDATE a = t.a + n.a, b = VALUES(a)', 'INSERT INTO `t`(`id`, `a`) VALUES (1, 1) AS `n` ON DUPLICATE KEY UPDATE `a` = (`t`.`a` + `n`.`a`), `b` = VALUES (`a`)'])]
    public function testBindResolvesDuplicateKeyReferencesToTheProposedRow(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\InsertStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        self::assertSame('n', $statement->policy->rowAlias?->row->alias);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n(x) ON DUPLICATE KEY UPDATE a = x'])]
    #[TestWith(['INSERT INTO t VALUES (1, 1, 1) AS n(x, y) ON DUPLICATE KEY UPDATE a = y'])]
    #[TestWith(['INSERT INTO t SET id = 1 AS n(p, q) ON DUPLICATE KEY UPDATE b = q'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n(x, X) ON DUPLICATE KEY UPDATE a = 1'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS t ON DUPLICATE KEY UPDATE a = 3'])]
    public function testBindDiagnosesAnImpossibleRowAlias(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::InsertRowAlias->message());
        $binder->bind($sql);
    }

    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n ON DUPLICATE KEY UPDATE a = n.b'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n ON DUPLICATE KEY UPDATE a = a + 1'])]
    #[TestWith(['INSERT INTO t (id, a) VALUES (1, 1) AS n ON DUPLICATE KEY UPDATE n.a = 1'])]
    public function testBindKeepsNamesTheServerRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)'));
        $this->expectException(SemanticException::class);
        $binder->bind($sql);
    }

    public function testBindReturnsNullWithoutAnAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE id = 2');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        self::assertNull($statement->policy->rowAlias);
    }

    public function testColumnsFollowTheInsertedColumnsOfAnUnknownTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $listed = $binder->bind('INSERT INTO u (id, a) VALUES (1, 1) AS n(p, q) ON DUPLICATE KEY UPDATE a = n.q', strict: false);
        self::assertInstanceOf(InsertValuesStatement::class, $listed);
        $columns = RowAliasBinder::columns($listed->insertion, $listed->origin->source);
        self::assertSame(['id', 'a'], array_column($columns, 'name'));
        self::assertSame('unknown', $columns[0]->type->name);
        $implicit = $binder->bind('INSERT INTO u VALUES (1) AS n(x) ON DUPLICATE KEY UPDATE a = x', strict: false);
        self::assertInstanceOf(InsertValuesStatement::class, $implicit);
        self::assertInstanceOf(MySqlInsertion::class, $implicit->policy);
        self::assertSame(['x'], array_column($implicit->policy->rowAlias?->row->declaration->columns ?? [], 'name'));
        self::assertSame('INSERT INTO `u` VALUES (1) AS `n`(`x`) ON DUPLICATE KEY UPDATE `a` = `x`', $implicit->toString());
    }

    public function testScopeAddsTheProposedRowBesideTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) AS n');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), [$statement->insertion->target]);
        self::assertSame($scope, RowAliasBinder::scope($scope, null));
        $extended = RowAliasBinder::scope($scope, $statement->policy->rowAlias);
        self::assertSame([$statement->insertion->target, $statement->policy->rowAlias?->row], $extended->relations);
    }

    public function testDestinationsKeepOnlyTheTargetBesideARowAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) AS n');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), [$statement->insertion->target]);
        self::assertNull(RowAliasBinder::destinations($scope, null));
        $extended = RowAliasBinder::scope($scope, $statement->policy->rowAlias);
        self::assertSame([$statement->insertion->target], RowAliasBinder::destinations($extended, $statement->policy->rowAlias)?->relations);
    }
}

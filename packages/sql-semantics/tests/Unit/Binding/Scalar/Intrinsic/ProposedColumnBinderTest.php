<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\ProposedColumnBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Reference\ProposedColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProposedColumnBinder::class)]
#[Medium]
final class ProposedColumnBinderTest extends TestCase
{
    public function testBindKeepsAQualifiedColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT VALUES(a.b)', strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(ProposedColumn::class, $value);
        self::assertSame(['a', 'b'], $value->column->referenceParts());
        self::assertSame('SELECT VALUES (`a`.`b`)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT VALUES(b)');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertNull(ProposedColumnBinder::bind($tree->find('simple_expr')[0], $scope));
    }

    public function testDestinationsLeaveOutTheNamedProposedRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT, a INT)'));
        $statement = $binder->bind('INSERT INTO t VALUES (1, 2) AS n ON DUPLICATE KEY UPDATE a = VALUES(a) + n.a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $statement->policy);
        $row = $statement->policy->rowAlias?->row;
        self::assertInstanceOf(\SqlSemantics\Model\Relation\ProposedRow::class, $row);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), [$statement->insertion->target, $row]);
        self::assertSame([$statement->insertion->target], ProposedColumnBinder::destinations($scope)->relations);
        $plain = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), [$statement->insertion->target]);
        self::assertSame($plain, ProposedColumnBinder::destinations($plain));
        self::assertSame('INSERT INTO `t` VALUES (1, 2) AS `n` ON DUPLICATE KEY UPDATE `a` = (VALUES (`a`) + `n`.`a`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}

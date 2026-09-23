<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Session\DoBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DoBinder::class)]
#[Medium]
final class DoBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsTypedScalarOperationsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('DO 1 + 2, 3 IS NOT UNKNOWN');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertCount(2, $statement->expressions);
        self::assertSame('bigint', $statement->expressions[0]->type->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindRetainsSubqueryScopeAndDiscardsUnobservableLabels(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DO (SELECT t.* FROM t) AS ignored');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $statement->expressions[0]);
        self::assertSame('id', $statement->expressions[0]->lineage()[0]->column->name);
        self::assertStringNotContainsString('ignored', $statement->toString());
    }

    #[TestWith(['DO *'])]
    #[TestWith(['DO t.*, 1'])]
    #[TestWith(['DO (1,2)'])]
    public function testBindDiagnosesNonScalarEvaluationRequests(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
    }

    public function testBindRetainsTheVariableAssignmentWithoutUpdatingTheSchema(): void
    {
        $type = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer');
        $variable = new \SqlSemantics\Schema\VariableDefinition('x', \SqlSemantics\Schema\VariableScope::User, $type, \SqlSemantics\Type\Nullability::MaybeNull);
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables($variable);
        $statement = (new Binder($schema))->bind('DO @x := 10, @x');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableAssignment::class, $statement->expressions[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableReference::class, $statement->expressions[1]);
        self::assertSame($variable, $statement->expressions[1]->definition);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $statement->expressions[1]->nullability);
    }

}

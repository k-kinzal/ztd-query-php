<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\LoadSource;
use SqlSemantics\Model\Statement\Loading\LoadTargets;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment\TupleRowAssignment;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadTargets::class)]
#[Medium]
final class LoadTargetsTest extends TestCase
{
    public function testSourceRequiresATextFileName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO 1')->origin;
        $name = Expression::literal('f', Dialect::MySql);
        $number = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        self::assertInstanceOf(Literal::class, $number);
        LoadTargets::source($origin, LoadSource::File, $name);
        $this->expectException(InvalidStructure::class);
        LoadTargets::source($origin, LoadSource::File, $number);
    }

    public function testSourceRejectsS3BeforeMySql82(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('DO 1')->origin;
        $name = Expression::literal('f', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $this->expectException(InvalidStructure::class);
        LoadTargets::source($origin, LoadSource::S3, $name);
    }

    public function testFieldsRejectsAnExpression(): void
    {
        $this->expectException(InvalidStructure::class);
        LoadTargets::fields([Expression::literal(1, Dialect::MySql)]);
    }

    public function testAssignmentsRejectATupleAssignment(): void
    {
        $update = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('UPDATE t SET (a, b) = (1, 2)');
        self::assertInstanceOf(UpdateTableStatement::class, $update);
        self::assertInstanceOf(TupleRowAssignment::class, $update->writes[0]);
        $this->expectException(InvalidStructure::class);
        LoadTargets::assignments($update->writes);
    }
}

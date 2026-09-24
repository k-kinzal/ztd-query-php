<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Operator\QualifiedOperatorBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Conditional\PatternMatch;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedInfixOperation;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedPrefixOperation;
use SqlSemantics\Model\Scalar\Operator\UnaryExpression;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QualifiedOperatorBinder::class)]
#[Medium]
final class QualifiedOperatorBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\Expression> $class
     */
    #[TestWith(['SELECT 1 OPERATOR(pg_catalog.+) 2', BinaryExpression::class, 'integer', 'SELECT (1 + 2)'])]
    #[TestWith(['SELECT 2 OPERATOR(*) 3 + 1', BinaryExpression::class, 'integer', 'SELECT (2 * (3 + 1))'])]
    #[TestWith(['SELECT OPERATOR(pg_catalog.-) 2', UnaryExpression::class, 'integer', 'SELECT (- 2)'])]
    #[TestWith(["SELECT 'a' OPERATOR(pg_catalog.!~~) 'b'", PatternMatch::class, 'boolean', "SELECT ('a' NOT LIKE 'b')"])]
    #[TestWith(['SELECT 1 OPERATOR(public.+) 2', QualifiedInfixOperation::class, 'unknown', 'SELECT (1 OPERATOR("public".+) 2)'])]
    #[TestWith(['SELECT 3 # 5', QualifiedInfixOperation::class, 'unknown', 'SELECT (3 OPERATOR(#) 5)'])]
    #[TestWith(['SELECT OPERATOR(pg_catalog.*) 2', QualifiedPrefixOperation::class, 'unknown', 'SELECT (OPERATOR("pg_catalog".*) 2)'])]
    #[TestWith(['SELECT |/ 25', QualifiedPrefixOperation::class, 'unknown', 'SELECT (OPERATOR(|/) 25)'])]
    public function testBindNormalizesBuiltinsAndKeepsOtherOperatorsNamed(string $sql, string $class, string $type, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame($type, $query->outputs[0]->expression->type->name);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindLeavesOtherExpressionsAlone(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1 + 2');
        self::assertNull(QualifiedOperatorBinder::bind($tree->find('a_expr')[0], new Scope(new Identifiers(Dialect::PostgreSql))));
        $mysql = (new DialectParser(Dialect::MySql))->parse('SELECT 1 + 2');
        self::assertNull(QualifiedOperatorBinder::bind($mysql->find('bit_expr')[0], new Scope(new Identifiers(Dialect::MySql))));
    }
}

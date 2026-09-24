<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Scalar\Function\FunctionName;
use SqlSemantics\Model\Scalar\Function\UnresolvedFunction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnresolvedFunction::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UnresolvedFunctionTest extends TestCase
{
    public function testNameReturnsTheRetainedIdentifier(): void
    {
        $name = new FunctionName(['app', 'total']);
        $function = new UnresolvedFunction($name);
        self::assertSame($name, $function->name());
        self::assertSame($name, $function->function);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT `left`(1, 2)', \SqlSemantics\Model\Scalar\Function\FunctionLookup::Name])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT LEFT(1, 2)', \SqlSemantics\Model\Scalar\Function\FunctionLookup::Grammar])]
    public function testNameKeepsHowMySqlFindsTheFunction(string $sql, \SqlSemantics\Model\Scalar\Function\FunctionLookup $lookup): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertInstanceOf(UnresolvedFunction::class, $call->function);
        self::assertSame($lookup, $call->function->lookup);
        self::assertSame('left', strtolower($call->function->name()->parts[0]));
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindingAnUnregisteredFunctionKeepsItsQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT app.total(1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertInstanceOf(UnresolvedFunction::class, $call->function);
        self::assertSame(['app', 'total'], $call->function->name()->parts);
        self::assertSame('SELECT "app"."total"(1)', $statement->toString());
    }
}

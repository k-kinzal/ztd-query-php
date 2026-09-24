<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\AllRowsAggregate;
use SqlSemantics\Model\Scalar\Function\DeclaredFunction;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Scalar\Function\FunctionReference;
use SqlSemantics\Model\Scalar\Function\UnresolvedFunction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FunctionReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FunctionReferenceTest extends TestCase
{
    public function testNameIsAvailableWhetherOrNotTheFunctionResolved(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT count(*), app.total(1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $resolved = $statement->outputs[0]->expression;
        self::assertInstanceOf(AllRowsAggregate::class, $resolved);
        $unresolved = $statement->outputs[1]->expression;
        self::assertInstanceOf(FunctionCall::class, $unresolved);
        self::assertInstanceOf(DeclaredFunction::class, $resolved->function);
        self::assertInstanceOf(UnresolvedFunction::class, $unresolved->function);
        self::assertSame(['count'], $resolved->function->name()->parts);
        self::assertSame(['app', 'total'], $unresolved->function->name()->parts);
    }
}

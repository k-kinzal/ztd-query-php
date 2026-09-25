<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Function\ArgumentNotations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\Argument\NamedArgument;
use SqlSemantics\Model\Scalar\Function\Argument\VariadicArgument;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ArgumentNotations::class)]
#[Medium]
final class ArgumentNotationsTest extends TestCase
{
    public function testApplyKeepsNamesAndVariadicMarksOfNestedCalls(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT f(x => g(y := 1), VARIADIC z => ARRAY[2])');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertInstanceOf(NamedArgument::class, $call->arguments[0]);
        self::assertInstanceOf(VariadicArgument::class, $call->arguments[1]);
        $inner = $call->arguments[0]->value;
        self::assertInstanceOf(FunctionCall::class, $inner);
        self::assertInstanceOf(NamedArgument::class, $inner->arguments[0]);
        self::assertSame('y', $inner->arguments[0]->name);
        self::assertSame('SELECT "f"("x" => "g"("y" => 1), VARIADIC "z" => ARRAY[2])', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testApplyLeavesPositionalCallsUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CONCAT(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame(['1', '2'], array_map(static fn ($argument): ?string => $argument->spelling(), $call->arguments));
    }

    #[TestWith(['SELECT f(a => 1, 2)'])]
    #[TestWith(['SELECT f(a => 1, a := 2)'])]
    public function testApplyRejectsAnImpossibleArgumentOrder(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::FunctionArgumentNotation->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testWrittenMarksTheArgumentAfterVariadic(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT f(1, a => 2, VARIADIC ARRAY[3])');
        $application = Tree::outer($tree, ['func_application'])[0];
        $written = ArgumentNotations::written($application);
        self::assertSame(['1', 'a => 2', 'ARRAY [ 3 ]'], array_map(static fn (array $argument): string => Tree::text($argument[0]), $written));
        self::assertSame([false, false, true], array_column($written, 1));
    }

    #[TestWith(['SELECT f(1, variadic ARRAY[2])', 'SELECT "f"(1, VARIADIC ARRAY[2])'])]
    #[TestWith(['SELECT f(1, 2)', 'SELECT "f"(1, 2)'])]
    #[TestWith(['SELECT f(1, x => 2)', 'SELECT "f"(1, "x" => 2)'])]
    public function testApplySpellsTheWrittenNotation(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }
}

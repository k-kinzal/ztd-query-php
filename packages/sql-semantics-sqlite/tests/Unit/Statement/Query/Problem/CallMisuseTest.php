<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallMisuse::class)]
#[Medium]
final class CallMisuseTest extends TestCase
{
    /**
     * @return array<string, array{CallMisuseRule, string}>
     */
    public static function providerMessages(): array
    {
        return [
            'window without over' => [CallMisuseRule::WindowWithoutOver, 'misuse of window function f()'],
            'scalar as window' => [CallMisuseRule::ScalarAsWindow, 'f() may not be used as a window function'],
            'filter without aggregate' => [CallMisuseRule::FilterWithoutAggregate, 'FILTER may not be used with non-aggregate f()'],
            'filter on window only' => [CallMisuseRule::FilterOnWindowOnly, 'FILTER clause may only be used with aggregate window functions'],
            'order by without aggregate' => [CallMisuseRule::OrderByWithoutAggregate, 'ORDER BY may not be used with non-aggregate f()'],
            'distinct in window' => [CallMisuseRule::DistinctInWindow, 'DISTINCT is not supported for window functions'],
            'distinct arguments' => [CallMisuseRule::DistinctArguments, 'DISTINCT aggregates must have exactly one argument'],
        ];
    }

    #[DataProvider('providerMessages')]
    public function testMessageUsesTheWordsOfSqliteForEachRule(CallMisuseRule $rule, string $message): void
    {
        $problem = new CallMisuse($rule, new Name('f'));

        self::assertSame($message, $problem->message());
        self::assertSame($rule, $problem->rule);
        self::assertSame('f', $problem->function->value);
    }

    public function testMessageNamesTheFunctionAsWritten(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT ABS(1) OVER ()');

        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[0]);
        self::assertSame('ABS() may not be used as a window function', $query->facts->diagnostics[0]->message());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;

#[CoversClass(ArityMismatch::class)]
#[Medium]
final class ArityMismatchTest extends TestCase
{
    /**
     * @return array<string, array{ArityRule, int, int, string}>
     */
    public static function providerMessages(): array
    {
        return [
            'compound arms' => [ArityRule::CompoundArms, 1, 2, 'SELECTs to the left and right of a compound operator do not have the same number of result columns: 1 and 2.'],
            'value rows' => [ArityRule::ValueRows, 2, 1, 'All VALUES must have the same number of terms: 2 and 1.'],
            'inserted values' => [ArityRule::InsertedValues, 2, 1, '1 values for 2 columns.'],
            'common table columns' => [ArityRule::CommonTableColumns, 2, 1, 'A common table has 1 values for 2 columns.'],
            'row comparison' => [ArityRule::RowComparison, 2, 3, 'Row value misused: 2 columns against 3.'],
            'row assignment' => [ArityRule::RowAssignment, 2, 3, '2 columns assigned 3 values.'],
            'scalar subquery' => [ArityRule::ScalarSubquery, 1, 2, 'Sub-select returns 2 columns - expected 1.'],
            'in list element of one term' => [ArityRule::InListElement, 2, 1, 'IN(...) element has 1 term - expected 2.'],
            'in list element of three terms' => [ArityRule::InListElement, 2, 3, 'IN(...) element has 3 terms - expected 2.'],
        ];
    }

    #[DataProvider('providerMessages')]
    public function testMessageDescribesTheDisagreementForEachRule(ArityRule $rule, int $expected, int $actual, string $message): void
    {
        $problem = new ArityMismatch($rule, $expected, $actual);

        self::assertSame($message, $problem->message());
        self::assertSame($rule, $problem->rule);
        self::assertSame($expected, $problem->expected);
        self::assertSame($actual, $problem->actual);
    }

    public function testMessageIsReportedForArmsOfDifferentWidth(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1, 2 UNION SELECT 3');

        self::assertInstanceOf(ArityMismatch::class, $query->facts->diagnostics[0]);
        self::assertSame(2, $query->facts->diagnostics[0]->expected);
        self::assertSame(1, $query->facts->diagnostics[0]->actual);
        self::assertSame('SELECTs to the left and right of a compound operator do not have the same number of result columns: 2 and 1.', $query->facts->diagnostics[0]->message());
    }
}

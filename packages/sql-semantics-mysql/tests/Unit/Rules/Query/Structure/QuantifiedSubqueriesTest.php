<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\QuantifiedSubqueries;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(QuantifiedSubqueries::class)]
#[Medium]
final class QuantifiedSubqueriesTest extends TestCase
{
    public function testProblemNamesTheUnsupportedCombination(): void
    {
        $limited = new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new RowLimit(new NumberLiteral('1')));

        self::assertStringContainsString("'LIMIT & IN/ALL/ANY/SOME subquery'", (new QuantifiedSubqueries())->problem($limited)?->message() ?? '');
        self::assertNull((new QuantifiedSubqueries())->problem(new Select([], [new SelectExpression(new NumberLiteral('1'))])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'IN' => ['SELECT 1 WHERE 1 IN (SELECT 1 FROM missing_t LIMIT 1)'];
        yield 'NOT IN with an offset' => ['SELECT 1 WHERE 1 NOT IN (SELECT 1 LIMIT 1 OFFSET 0)'];
        yield 'ALL' => ['SELECT 1 WHERE 1 > ALL (SELECT 1 LIMIT 1)'];
        yield 'row operand' => ['SELECT 1 WHERE ROW(1, 2) IN (SELECT 1, 2 LIMIT 1)'];
        yield 'parenthesized' => ['SELECT 1 WHERE 1 IN ((SELECT 1) LIMIT 1)'];
        yield 'union' => ['SELECT 1 WHERE 1 IN (SELECT 1 UNION SELECT 2 LIMIT 1)'];
        yield 'in a body' => ['CREATE PROCEDURE p() BEGIN SELECT 1 WHERE 1 IN (SELECT 1 LIMIT 1); END'];
    }

    #[DataProvider('providerRefusals')]
    public function testProblemIsRefusedOnceTheStatementIsRead(string $sql): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('No release executes a LIMIT in the subquery of IN, ANY, ALL or SOME.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet::class, $failure->problem);
            self::assertStringContainsString("'LIMIT & IN/ALL/ANY/SOME subquery'", $failure->problem->message());
            self::assertFalse($failure->aborts);
        }
        self::assertSame('SELECT 1 WHERE EXISTS (SELECT 1 LIMIT 1) AND (SELECT 1 LIMIT 1) AND 1 IN (SELECT 1 FROM (SELECT 1 LIMIT 1) AS d)', (new Semantics(Dialect::MySql))->analyze('SELECT 1 WHERE EXISTS (SELECT 1 LIMIT 1) AND (SELECT 1 LIMIT 1) AND 1 IN (SELECT 1 FROM (SELECT 1 LIMIT 1) AS d)')->toString());
    }
}

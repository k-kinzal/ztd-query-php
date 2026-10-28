<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Eris\Quantifier\ForAll;
use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use Eris\Shrinker\ShrinkerFactory;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

#[CoversNothing]
#[Large]
final class QueryConstraintShrinkingTest extends TestCase
{
    public function testShrinkingRetainsTheReadConditionAndReducesItsRowOperand(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1793157892254404);
        $quantifier = new ForAll([QueryRootGenerator::constrainedReads(GrammarRelease::MySql5651)], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional read-condition counterexample');
        try {
            $quantifier->when(static function (array $sample): bool {
                self::assertIsArray($sample[0]);
                return $sample[0][0] === 3;
            })->then(static function (array $sample) use (&$attempts, &$last): void {
                [, $statement] = $sample;
                self::assertInstanceOf(QueryRoot::class, $statement);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $statement->query);
                $row = $statement->query->selection->where;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Row::class, $row);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $row->elements[0]);
                $last = $row->elements[0]->text;
                try {
                    (new Execution((new Instance('5.6.51', databases: ['pbt']))->connect(database: 'pbt')))->statement($statement);
                    self::fail('The generated row condition must prevent execution.');
                } catch (SqlError $error) {
                    self::assertSame(1241, $error->getCode());
                }
                $attempts++;
                self::fail('Intentional read-condition counterexample');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame('0', $last);
        }
    }
}

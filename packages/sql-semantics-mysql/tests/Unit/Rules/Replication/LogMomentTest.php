<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\LogMoment;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Statement\Scalar;

#[CoversClass(LogMoment::class)]
#[Small]
final class LogMomentTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerMoments(): iterable
    {
        foreach (['scalar', 'exists', 'in', 'any'] as $kind) {
            foreach ([false, true] as $nested) {
                $query = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
                $moment = match ($kind) {
                    'scalar' => new ScalarSubquery($query),
                    'exists' => new Exists($query),
                    'in' => new InQuery(new NumberLiteral('1'), $query),
                    'any' => new QuantifiedComparison(new NumberLiteral('1'), ComparisonOperator::Equal, Quantifier::Any, $query),
                };
                yield $kind . ($nested ? ' in unreachable arm' : '') => [$nested ? new CaseExpression(null, [new CaseBranch(new NumberLiteral('0'), $moment)], new NumberLiteral('0')) : $moment];
            }
        }
    }

    #[DataProvider('providerMoments')]
    public function testCheckPreventsPublishingAQueryBearingPurge(Scalar $moment): void
    {
        $this->expectExceptionMessage('A PURGE moment cannot contain a subquery.');

        new PurgeLogsBefore($moment);
    }

    public function testCheckRetainsOrdinaryScalarExpressions(): void
    {
        $moment = new CaseExpression(null, [new CaseBranch(new NumberLiteral('0'), new NumberLiteral('1'))], new NumberLiteral('0'));
        (new LogMoment())->check($moment);

        self::assertSame($moment, (new PurgeLogsBefore($moment))->moment);
    }

    #[DataProvider('providerMoments')]
    public function testProblemDescribesAQueryBearingMomentAndAcceptsALiteral(Scalar $moment): void
    {
        $rules = new LogMoment();

        self::assertSame('A PURGE moment cannot contain a subquery.', $rules->problem($moment));
        self::assertNull($rules->problem(new NumberLiteral('1')));
    }
}

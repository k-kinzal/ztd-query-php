<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;

#[CoversClass(Quantifier::class)]
#[Small]
final class QuantifierTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ALL', 'ANY', 'SOME'], array_column(Quantifier::cases(), 'value'));
    }
    /**
     * @return iterable<string, array{Quantifier, \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator, bool}>
     */
    public static function providerRowComparisons(): iterable
    {
        foreach (Quantifier::cases() as $quantifier) {
            foreach (\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::cases() as $operator) {
                yield $quantifier->value . '/' . $operator->value => [$quantifier, $operator, in_array([$quantifier->value, $operator->value], [['ANY', '='], ['SOME', '='], ['ALL', '<>']], true)];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRowComparisons')]
    public function testComparesRowsExposesTheOperatorConstraint(Quantifier $quantifier, \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator $operator, bool $expected): void
    {
        self::assertSame($expected, $quantifier->comparesRows($operator));
    }
}

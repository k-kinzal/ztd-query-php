<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Predicate\Projection;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ProjectionTest extends TestCase
{
    public function testValueConvertsPhpFalseyAndTruthyCandidates(): void
    {
        $values = [false, 0, -0.0, '', '0', null, [], 'false', -1, [0]];
        $truths = array_map(static fn ($value) => (new Projection())->value(Term::fromNative($value))->native(), $values);
        self::assertSame([false,false,false,false,false,false,false,true,true,true], $truths);
    }

    public function testCollectStopsAfterBothWitnessesWithoutOpeningRemainingDependencies(): void
    {
        $opened = false;
        $result = (new Projection())->collect([
            static fn (): Term => Term::constant(0),
            static fn (): Term => Term::constant('yes'),
            static function () use (&$opened): Term {
                $opened = true;
                return Term::constant(4);
            },
        ]);
        self::assertSame([false,true], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($result))));
        self::assertFalse($opened);
    }

    public function testCollectRetainsUnknownsAlongsideAnInsufficientSingleton(): void
    {
        $missing = new Term(TermKind::Deferred, 'input', attributes: ['reason' => 'BUDGET_EXCEEDED']);
        $value = (new Projection())->collect([static fn (): Term => Term::constant(false), static fn (): Term => $missing]);
        $rows = iterator_to_array((new Choices())->alternatives($value));
        self::assertCount(2, $rows);
        self::assertSame(false, $rows[0][0]->native());
        self::assertSame($missing, $rows[1][0]->operands[0]);
    }

    public function testCollectAsksNestedAlternativesOnlyForTheMissingTruth(): void
    {
        $requested = [];
        $value = (new Projection())->collect([static fn (): Term => Term::constant(0), static function (int $wanted = 3) use (&$requested): Term {
            $requested[] = $wanted;
            return Term::constant(1);
        }]);
        self::assertSame([2], $requested);
        self::assertCount(2, iterator_to_array((new Choices())->alternatives($value)));
    }
}

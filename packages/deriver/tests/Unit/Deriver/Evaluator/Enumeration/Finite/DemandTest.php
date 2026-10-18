<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Enumeration\Finite;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Enumeration\Finite\Demand;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Provenance;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DemandTest extends TestCase
{
    public function testDemandKeepsRemainingMembersInItsCacheIdentity(): void
    {
        $all = Demand::truth();
        $one = Demand::truth(1);
        self::assertNotSame($all->identity, $one->identity);
        self::assertSame(['b:0' => true, 'b:1' => true], $all->remaining);
    }

    public function testTruthUsesOnlyTheRequestedBooleanMembers(): void
    {
        self::assertSame(['b:1' => true], Demand::truth(2)->remaining);
        self::assertSame('b:0', Demand::truth()->key(Term::constant('0')));
        self::assertSame('b:1', Demand::truth()->key(Term::constant('yes')));
    }

    public function testCollectDoesNotOpenAlternativesAfterCoverage(): void
    {
        $opened = 0;
        $value = Demand::truth()->collect([
            static fn (): Term => Term::constant(0),
            static fn (): Term => Term::constant(7),
            static function () use (&$opened): Term {
                ++$opened;
                return Term::constant(9);
            },
        ], new Provenance());
        self::assertSame([0,7], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($value))));
        self::assertSame(0, $opened);
    }

    public function testCollectRetainsObservedFailuresAfterCoverage(): void
    {
        $failure = new Term(TermKind::Throwable, 'TypeError');
        $value = Demand::truth()->collect([
            static fn (): Term => $failure,
            static fn (): Term => Term::constant(false),
            static fn (): Term => Term::constant(true),
        ], new Provenance());
        $values = array_map(static fn (array $row): Term => $row[0], iterator_to_array((new Choices())->alternatives($value)));
        self::assertContains($failure, $values);
        self::assertCount(3, $values);
    }

    public function testKeyDoesNotInventAValueFromItsDeclaredType(): void
    {
        self::assertNull(Demand::truth()->key(Term::parameter('x', 'bool')));
    }

    public function testIdentityDistinguishesEnumCasesWithEqualBackingValues(): void
    {
        $first = new Term(TermKind::Enum, 'First::A');
        $second = new Term(TermKind::Enum, 'Second::A');
        self::assertNotSame(Demand::identity($first), Demand::identity($second));
        self::assertNull(Demand::identity(Term::parameter('x')));
    }

    public function testThroughChecksStrictDeclarationsBeforeCountingWitnesses(): void
    {
        $source = 'function subject(){return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $strict = Demand::truth()->through($engine, 'bool', true);
        $weak = Demand::truth()->through($engine, 'bool', false);
        self::assertNull($strict->key(Term::constant(1)));
        self::assertSame('b:1', $weak->key(Term::constant(1)));
        $invalid = Term::constant(1);
        $candidates = $strict->collect([
            static fn (): Term => $invalid,
            static fn (): Term => Term::constant(false),
            static fn (): Term => Term::constant(true),
        ], $engine->context->provenance);
        self::assertContains($invalid, array_map(static fn (array $row): Term => $row[0], iterator_to_array((new Choices())->alternatives($candidates))));
    }
}

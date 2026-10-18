<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FiniteCandidatesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<scalar|null>, bool}>
     */
    public static function providerClosedDomains(): iterable
    {
        yield 'declared boolean' => ['function subject($x):bool{return $x?false:($x?true:expensive());}', [false,true], false];
        yield 'typed predicate return' => ['function foo($x):bool{return $x?false:($x?true:expensive());}function subject($x){if(foo($x))return 1;return 2;}', [1,2], true];
        yield 'typed predicate argument' => ['function foo(bool $x):bool{return $x;}function subject($x){if(foo($x?false:($x?true:expensive())))return 1;return 2;}', [1,2], true];
        yield 'weak boolean conversions' => ['function subject($x):bool{return $x?0:($x?"yes":expensive());}', [false,true], false];
        yield 'nullable boolean' => ['function subject($x):?bool{return $x?null:($x?false:($x?true:expensive()));}', [null,false,true], false];
        yield 'boolean storage' => ['function subject($x):bool{$v=$x?false:($x?true:expensive());return $v;}', [false,true], false];
        yield 'boolean helper' => ['function helper($x){return $x?false:($x?true:expensive());}function subject($x):bool{return helper($x);}', [false,true], false];
        yield 'enum cases' => ['enum State{case A;case B;}function subject($x):State{return $x?State::A:($x?State::B:expensive());}', ['State::A','State::B'], false];
        yield 'backed enum cases' => ['enum State:string{case A="a";case B="b";}function subject($x):State{return $x?State::A:($x?State::B:expensive());}', ['State::A','State::B'], false];
        yield 'enum helper' => ['enum State{case A;case B;}function helper($x){return $x?State::A:($x?State::B:expensive());}function subject($x):State{return helper($x);}', ['State::A','State::B'], false];
        yield 'nullable enum' => ['enum State{case A;case B;}function subject($x):?State{return $x?null:($x?State::A:($x?State::B:expensive()));}', [null,'State::A','State::B'], false];
    }

    /**
     * @param list<scalar|null> $expected
     */
    #[DataProvider('providerClosedDomains')]
    public function testStopsAtCompleteDomain(string $source, array $expected, bool $pruning): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $opened = 0;
        $evaluators = [...($pruning ? BranchEvaluators::truth() : []), new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(false);
        })];
        $result = $derive($source, ...$evaluators);
        $values = array_map(static fn ($candidate) => $candidate->result instanceof Term && $candidate->result->kind === TermKind::Enum ? $candidate->result->literal : $candidate->result, iterator_to_array($result));
        self::assertEqualsCanonicalizing($expected, $values);
        self::assertSame(0, $opened);
        self::assertFalse($result->interrupted);
    }

    public function testATypeBoundDoesNotInventMissingWitnesses(): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $opened = 0;
        $source = 'function subject($x):bool{return $x?false:expensive();}';
        $result = $derive($source, ...[new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(true);
        })]);
        self::assertSame(1, $opened);
        self::assertEqualsCanonicalizing([false,true], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        $singleton = $derive('function subject():bool{return false;}');
        self::assertCount(1, $singleton);
        self::assertFalse($singleton[0]->result);
    }

    public function testTypedTruthStillRejectsInvalidStrictInputs(): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $result = $derive('declare(strict_types=1);function foo(bool $x):bool{return $x;}function subject(){return foo(1);}', ...BranchEvaluators::truth());
        self::assertNotEmpty(iterator_to_array($result));
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame(TermKind::NoValue, $result[0]->result->kind);
    }

}

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
final class LoopPruningTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerLoops(): iterable
    {
        yield 'zero while' => ['function subject(){$v=0;while(false){$v=expensive();}return $v;}', [0]];
        yield 'zero for' => ['function subject(){$v=0;for($i=0;$i<0;$i++){$v=expensive();}return $v;}', [0]];
        yield 'one for' => ['function subject(){$v=0;for($i=0;$i<1;$i++){$v=$i===0?11:expensive();}return $v;}', [11]];
        yield 'one while' => ['function subject(){$v=0;$i=0;while($i<1){$v=$i===0?11:expensive();$i++;}return $v;}', [11]];
        yield 'one do' => ['function subject(){$v=0;$i=0;do{$v=$i===0?11:expensive();$i++;}while($i<1);return $v;}', [11]];
        yield 'one boolean loop' => ['function subject(){$v=0;$run=true;while($run){$v=$run?11:expensive();$run=false;}return $v;}', [11]];
        yield 'empty foreach' => ['function subject(){$v=0;foreach([] as $x){$v=expensive();}return $v;}', [0]];
        yield 'one foreach' => ['function subject(){$v=0;foreach([5] as $x){$v=$x===5?11:expensive();}return $v;}', [11]];
        yield 'body return' => ['function subject(){for($i=0;$i<1;$i++){return $i===0?11:expensive();}return 0;}', [11]];
        yield 'conditional body return' => ['function subject($x){for($i=0;$i<1;$i++){if($x){return $i===0?11:expensive();}}return 0;}', [0,11]];
        yield 'unknown count' => ['function subject($x){$v=0;while(condition()){$v=$x?11:27;}return $v;}', [0,11,27]];
        yield 'independent expressions' => ['function subject($x):int{$a=$x>0?11:27;$b=$x>13?1:4;return $a+$b;}', [12,15,28,31]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('providerLoops')]
    public function testPrunesWithoutOpeningDiscardedCandidates(string $source, array $expected): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $opened = 0;
        $result = $derive($source, ...[...BranchEvaluators::truth(), new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(27);
        })]);
        self::assertEqualsCanonicalizing($expected, array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame(0, $opened);
        self::assertFalse($result->interrupted);
    }

    public function testLoopCountDoesNotMultiplyCandidateExpansion(): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $short = $derive('function subject(){$v=0;for($i=0;$i<2;$i++){$v=$i===0?11:27;}return $v;}', ...BranchEvaluators::truth());
        $long = $derive('function subject(){$v=0;for($i=0;$i<10000;$i++){$v=$i===0?11:27;}return $v;}', ...BranchEvaluators::truth());
        self::assertEqualsCanonicalizing([11,27], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($short)));
        self::assertEqualsCanonicalizing([11,27], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($long)));
        self::assertSame($short->statistics->constructedNodes, $long->statistics->constructedNodes);
        self::assertSame($short->statistics->referenceExpansions, $long->statistics->referenceExpansions);
    }

    public function testDefaultCandidatesDoNotInspectLoopBounds(): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $result = $derive('function subject(){$v=0;for($i=0;$i<1;$i++){$v=$i===0?11:27;}return $v;}');
        self::assertEqualsCanonicalizing([0,11,27], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame(1, $result->statistics->referenceExpansions);
    }
    public function testDefaultLoopCandidatesRetainConditionalBodyReturns(): void
    {
        $derive = static function (string $source, MicroEvaluator ...$evaluators): \Deriver\Deriver\Candidate\CandidateCollection {
            $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
            $program = new Index('fixture', [$tree]);
            $target = new TargetProfile();
            $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), array_values($evaluators)));
            return (new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment')))->derive(new ReturnQuery('subject'));
        };
        $result = $derive('function subject($x){for($i=0;$i<1;$i++){if($x){return $i===0?11:27;}}return 0;}');
        self::assertEqualsCanonicalizing([0,11,27], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertFalse($result->interrupted);
    }

}

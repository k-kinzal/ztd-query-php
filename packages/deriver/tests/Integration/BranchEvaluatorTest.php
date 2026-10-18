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
final class BranchEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerSelections(): iterable
    {
        yield 'standard evaluator scalar' => ['function subject(){return strlen(strtoupper("abc"));}', [3]];
        yield 'standard evaluator filter' => ['function subject(){return count(array_filter([0,1,2]));}', [2]];
        yield 'standard evaluator reduce' => ['function subject(){return array_reduce([1,2],fn($carry,$v)=>$carry+$v,0);}', [3]];
        yield 'standard evaluator map' => ['function subject(){return array_map(fn($v)=>$v*2,[1,2])[1];}', [4]];
        yield 'if false' => ['function subject(){if(false)return expensive();return 2;}', [2]];
        yield 'if true' => ['function subject(){if(true)return 1;return expensive();}', [1]];
        yield 'elseif' => ['function subject(){if(false)return expensive();elseif(true)return 2;return expensive();}', [2]];
        yield 'ternary' => ['function subject(){return false?expensive():2;}', [2]];
        yield 'nested ternary' => ['function subject(){return (true?0:expensive())?expensive():2;}', [2]];
        yield 'while false' => ['function subject(){while(false){return expensive();}return 2;}', [2]];
        yield 'while write' => ['function subject(){$v=2;while(false){$v=expensive();}return $v;}', [2]];
        yield 'while break' => ['function subject(){$v=0;while(true){$v=2;break;}return $v;}', [2]];
        yield 'changing loop input' => ['function subject(){$v=true;while($v){$v=false;}return 2;}', [2]];
        yield 'for false' => ['function subject(){for(start();false;advance()){return expensive();}return 2;}', [2]];
        yield 'for write' => ['function subject(){for($v=2;false;advance()){$v=expensive();}return $v;}', [2]];
        yield 'foreach empty' => ['function subject(){foreach([] as $v){return expensive();}return 2;}', [2]];
        yield 'foreach values' => ['function subject(){foreach([1,2] as $v){return $v;}return 3;}', [1,2,3]];
        yield 'foreach empty write' => ['function subject(){$v=2;foreach([] as $x){$v=expensive();}return $v;}', [2]];
        yield 'do first write' => ['function subject(){$v=0;do{$v=2;}while(false);return $v;}', [2]];
        yield 'do first return' => ['function subject(){do{return 2;}while(false);return expensive();}', [2]];
        yield 'switch' => ['function subject(){switch(2){case 1:return expensive();case 2:return 2;default:return expensive();}}', [2]];
        yield 'match' => ['function subject(){return match(2){1=>expensive(),2=>2,default=>expensive()};}', [2]];
        yield 'falsey zero' => ['function subject(){if(0)return expensive();return 2;}', [2]];
        yield 'falsey string' => ['function subject(){if("0")return expensive();return 2;}', [2]];
        yield 'falsey empty string' => ['function subject(){if("")return expensive();return 2;}', [2]];
        yield 'falsey null' => ['function subject(){if(null)return expensive();return 2;}', [2]];
        yield 'falsey float' => ['function subject(){if(-0.0)return expensive();return 2;}', [2]];
        yield 'falsey array' => ['function subject(){if([])return expensive();return 2;}', [2]];
        yield 'truthy string' => ['function subject(){if("false")return 2;return expensive();}', [2]];
        yield 'truthy array' => ['function subject(){if([0])return 2;return expensive();}', [2]];
        yield 'both boolean values' => ['function subject($x){if($x?0:4)return 1;return 2;}', [1,2]];
        yield 'unknown keeps both' => ['function subject($x){if($x)return 1;return 2;}', [1,2]];
        yield 'unknown plus singleton keeps both' => ['function subject($x){if($x?0:missing())return 1;return 2;}', [1,2]];
        yield 'full values remain independent' => ['function subject($x){$v=$x?1:($x?2:0);if($v)return $v;return 3;}', [0,1,2,3]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('providerSelections')]
    public function testPrunesOnlyExcludedBranches(string $source, array $expected): void
    {
        $evaluators = BranchEvaluators::truth();
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        sort($values);
        self::assertSame($expected, $values);
        self::assertArrayNotHasKey('expensive', $result->statistics->expandedBodies);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSaturation(): iterable
    {
        yield 'return alternatives' => ['function values($x){if($x)return 0;if($x)return 1;return expensive();} function subject(){if(values())return 1;return 2;}'];
        yield 'ternary alternatives' => ['function subject($x){if($x?0:($x?1:expensive()))return 1;return 2;}'];
        yield 'local writes' => ['function subject($x){if($x){$v=0;}elseif($x){$v=1;}else{$v=expensive();}if($v)return 1;return 2;}'];
        yield 'lazy arguments' => ['function values($x){if($x)return 0;if($x)return 1;return expensive();} function condition($x){return $x;} function subject(){if(condition(values()))return 1;return 2;}'];
        yield 'caller origins' => ['function subject($x){if($x)return 1;return 2;} function first(){subject(0);} function second(){subject(1);} function third(){subject(expensive());}'];
    }

    #[DataProvider('providerSaturation')]
    public function testStopsOpeningDependenciesAfterBothTruths(string $source): void
    {
        $opened = 0;
        $evaluators = [...BranchEvaluators::truth(), new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(3);
        })];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        sort($values);
        self::assertSame([1,2], $values);
        self::assertSame(0, $opened);
    }

    public function testReplacesBranchSemanticsWithoutDemandingTheCondition(): void
    {
        $source = 'function subject(){if(expensive())return 1;return 2;}';
        $evaluators = [new MicroEvaluator('always.left', '1', EvaluationOperation::Branch, '', static fn (): Term => Term::constant(true))];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertSame(1, $result[0]->result);
        self::assertCount(1, $result);
        self::assertSame(0, $result->statistics->referenceExpansions);
        self::assertSame(1, $result->statistics->evaluatorApplications);
    }

    public function testDecliningABranchEvaluatorRetainsDefaultAlternatives(): void
    {
        $source = 'function subject(){return false?1:2;}';
        $evaluators = [new MicroEvaluator('decline', '1', EvaluationOperation::Branch, '', static fn (): ?Term => null)];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertSame([1,2], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame(0, $result->statistics->evaluatorApplications);
    }
    public function testEveryBranchRetainsTheAcceptedEvaluatorAndUnknownDependencyEvidence(): void
    {
        $source = 'function subject(){if(missing())return 1;return 2;}';
        $evaluators = BranchEvaluators::truth();
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        $evidence = array_map(static fn ($candidate): array => array_merge(...array_map(static fn ($alternative): array => $alternative->nodes(), $candidate->evidence)), iterator_to_array($result));
        self::assertSame([true,true], array_map(static fn (array $nodes): bool => array_filter($nodes, static fn ($node): bool => ($node->attributes['id'] ?? '') === 'deriver.branch.truth') !== [], $evidence));
        self::assertSame([true,true], array_map(static fn (array $nodes): bool => array_filter($nodes, static fn ($node): bool => ($node->attributes['reason'] ?? '') === 'MISSING_SOURCE') !== [], $evidence));
    }

    public function testAnInterruptedSingletonDoesNotPruneTheOtherBranch(): void
    {
        $source = 'function subject(){if(condition())return 1;return 2;}';
        $evaluators = [...BranchEvaluators::truth(), new MicroEvaluator('partial', '1', EvaluationOperation::Function, 'condition', static fn (): Term => (new \Deriver\Deriver\Evaluator\Enumeration\Choices())->make([[Term::constant(false), []], [new Term(\Deriver\Deriver\Evaluator\TermKind::Deferred, 'remaining', attributes: ['reason' => 'BUDGET_EXCEEDED']), []]]))];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertNotEmpty(array_filter(iterator_to_array($result), static fn ($candidate): bool => $candidate->result instanceof Term && $candidate->result->kind === \Deriver\Deriver\Evaluator\TermKind::ControlledValue && $candidate->result->operands[0]->native() === 1));
        self::assertNotEmpty(array_filter(iterator_to_array($result), static fn ($candidate): bool => $candidate->result === 2));
    }

}

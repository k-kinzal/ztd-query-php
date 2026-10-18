<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluation;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
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
final class CandidateControlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int|null>}>
     */
    public static function providerBranches(): iterable
    {
        yield 'independent ternaries' => ['function subject($x){$a=$x?1:2;$b=$x?10:20;return $a+$b;} function caller(){return subject(condition());}', [11,12,21,22]];
        yield 'constant condition' => ['function subject(){return false?1:2;}', [1,2]];
        yield 'if' => ['function subject(){if(condition()){return 7;}return 9;}', [7,9]];
        yield 'elseif' => ['function subject(){if(condition()){return 1;}elseif(condition()){return 2;}return 3;}', [1,2,3]];
        yield 'match' => ['function subject(){return match(condition()){caseValue()=>1,default=>2};}', [1,2]];
        yield 'switch' => ['function subject(){switch(condition()){case caseValue():return 1;default:return 2;}}', [1,2]];
        yield 'while' => ['function subject(){while(condition()){return 7;}return 9;}', [7,9]];
        yield 'for' => ['function subject(){for(start();condition();advance()){return 7;}return 9;}', [7,9]];
        yield 'foreach' => ['function subject(){foreach(source() as $v){return 7;}return 9;}', [7,9]];
        yield 'while overwrite' => ['function subject(){$v=1;while(condition()){$v=7;}return $v;}', [1,7]];
        yield 'for overwrite' => ['function subject(){$v=1;for(start();condition();advance()){$v=7;}return $v;}', [1,7]];
        yield 'foreach overwrite' => ['function subject(){$v=1;foreach(source() as $item){$v=7;}return $v;}', [1,7]];
        yield 'do overwrite' => ['function subject(){$v=1;do{$v=7;}while(condition());return $v;}', [7]];
        yield 'nested overwrite' => ['function subject(){$v=1;while(condition()){for(start();condition();advance()){$v=7;}}return $v;}', [1,7]];
        yield 'foreach values' => ['function subject(){foreach([3,7] as $v){return $v;}return 9;}', [3,7,9]];
        yield 'foreach last value' => ['function subject(){$v=1;foreach([3,7] as $v){}return $v;}', [1,3,7]];
        yield 'for initializer value' => ['function subject(){for($v=7;condition();advance()){return $v;}return 9;}', [7,9]];
        yield 'value assigned in condition' => ['function subject(){while($v=7){return $v;}return 9;}', [7,9]];
        yield 'break excludes an unreachable update' => ['function subject(){$v=0;while(true){$v=7;break;}return $v;}', [0,7]];
        yield 'loop in finally' => ['function subject(){try{return 9;}finally{$v=1;while(condition()){$v=7;}return $v;}}', [1,7]];
        yield 'loop in catch' => ['function subject(){try{throw new Exception();}catch(Exception $e){$v=1;while(condition()){$v=7;}return $v;}}', [1,7]];
        yield 'dependent loop storage' => ['function subject(){$x=1;$y=2;while(condition()){$x=$y;$y=7;}return $x;}', [1,2,7]];
    }

    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('providerBranches')]
    public function testCandidatesDoNotDemandPredicates(string $source, array $expected): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: []);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        sort($values);
        self::assertSame($expected, $values);
        self::assertFalse($result->interrupted);
        self::assertSame(1, $program->graphCount(), 'Neither predicate functions nor callers supplying only predicates are compiled.');
        self::assertSame([], $result->statistics->expandedBodies);
        $nodes = array_merge(...array_map(static fn ($candidate): array => array_merge(...array_map(static fn ($alternative): array => $alternative->nodes(), $candidate->evidence)), iterator_to_array($result)));
        self::assertNotContains(EvidenceKind::Unexpanded, array_column($nodes, 'kind'), 'Complete candidates do not acquire unresolved predicate dependencies.');
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function providerValueBranches(): array
    {
        return ['left' => [0, 'left', 'right'], 'right' => [1, 'right', 'left']];
    }

    #[DataProvider('providerValueBranches')]
    public function testEvidenceContainsOnlyTheSelectedValueBranch(int $offset, string $selected, string $unselected): void
    {
        $source = 'function subject(){return condition()?left():right();} function left(){return 1;} function right(){return 2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: []);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertCount(2, $result);
        $nodes = array_merge(...array_map(static fn ($alternative): array => $alternative->nodes(), $result[$offset]->evidence));
        $owners = array_column(array_column($nodes, 'attributes'), 'owner');
        self::assertContains($selected, $owners);
        self::assertNotContains($unselected, $owners);
        self::assertNotContains('condition', $owners);
    }

    public function testLoopCarriedValuesRetainAnUpdateWithoutDeterminingIterationCounts(): void
    {
        $source = 'function subject(){$v=1;while(condition()){$v+=2;}return $v;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: []);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertCount(1, $result);
        $term = $result[0]->result;
        self::assertInstanceOf(Term::class, $term);
        self::assertSame(TermKind::Recurrence, $term->kind);
        self::assertSame('LOOP_DEPENDENCY', $term->attributes['reason']);
        self::assertSame(1, $term->operands[0]->native());
        self::assertSame(TermKind::Binary, $term->operands[1]->kind);
        self::assertSame('+', $term->operands[1]->literal);
        self::assertSame(2, $term->operands[1]->operands[1]->native());
        self::assertSame(1, $program->graphCount());
        self::assertFalse($result->interrupted);
    }

    public function testForeachKeysStayPairedWithTheirValues(): void
    {
        $source = 'function subject(){foreach(["a"=>3,"b"=>7] as $k=>$v){return [$k,$v];}return [];}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: []);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        self::assertEqualsCanonicalizing([['a',3],['b',7],[]], $values);
    }

    public function testAnApplicationEvaluatorCanChooseToEvaluateAConditional(): void
    {
        $evaluator = new MicroEvaluator('app.conditional', '1', EvaluationOperation::Conditional, '', static fn (Evaluation $evaluation): Term => $evaluation->input($evaluation->input(2)->native() === true ? 0 : 1));
        $source = 'function subject(){return false?1:2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: [(new MicroEvaluatorConversion())->convert($evaluator)]);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertCount(1, $result);
        self::assertSame(2, $result[0]->result);
        self::assertSame(1, $result->statistics->evaluatorApplications);
    }

    public function testUnusedTypedPredicateArgumentsDoNotOpenTheirValues(): void
    {
        $source = 'function subject(){return helper(condition());} function helper(bool $flag){return $flag?1:2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('candidate-control.php', '<?php ' . $source)));
        $program = new Index('candidates', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: []);
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('candidates', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertSame([1,2], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame(['helper' => 1], $result->statistics->expandedBodies);
        self::assertSame([], $result->statistics->expandedReferences);
    }

}

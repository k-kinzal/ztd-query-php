<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluation;
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
final class CallableReplacementTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerCalls(): iterable
    {
        yield 'direct function' => ['function subject($unused){return expensive();}', 'subject', 'subject'];
        yield 'direct static method' => ['class Subject{static function run($unused){return expensive();}}', 'Subject::run', 'Subject::run'];
        yield 'direct instance method' => ['class Subject{function run($unused){return expensive();}}', 'Subject::run', 'Subject::run'];
        yield 'function call' => ['function replacement($unused){return expensive();} function subject(){return replacement(expensive());}', 'subject', 'replacement'];
        yield 'static method call' => ['class Subject{static function run($unused){return expensive();}} function subject(){return Subject::run(expensive());}', 'subject', 'Subject::run'];
        yield 'instance method call' => ['class Subject{function run($unused){return expensive();}} function subject(){return (new Subject())->run(expensive());}', 'subject', 'Subject::run'];
        yield 'conditional definition' => ['if(condition()){function subject(){return expensive();}}else{function subject(){return expensive();}}', 'subject', 'subject'];
    }

    #[DataProvider('providerCalls')]
    public function testReplacesTheSelectedCallableBeforeItsBody(string $source, string $symbol, string $replaced): void
    {
        $evaluators = [new MicroEvaluator('replace', '1', EvaluationOperation::Function, $replaced, static fn (): Term => Term::constant(42))];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery($symbol));
        self::assertSame([42], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame([], $result->statistics->expandedBodies);
        self::assertArrayNotHasKey($replaced . ':$unused', $result->statistics->expandedReferences);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function providerParameters(): iterable
    {
        yield 'position' => [0];
        yield 'name' => ['value'];
    }

    #[DataProvider('providerParameters')]
    public function testDirectReplacementDemandsOnlyItsSelectedParameter(int|string $key): void
    {
        $source = 'function subject($value,$unused){return expensive();} function caller(){return subject(42,expensive());}';
        $evaluators = [new MicroEvaluator('replace', '1', EvaluationOperation::Function, 'subject', static fn (Evaluation $evaluation): Term => $evaluation->input($key))];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('branches.php', '<?php ' . $source)));
        $program = new Index('branches', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $query = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('branches', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('subject'));
        self::assertSame([42], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame([], $result->statistics->expandedBodies);
    }
}

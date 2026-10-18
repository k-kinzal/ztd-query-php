<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Query;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\SelectorResolution;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CallPathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<scalar|null>, bool}>
     */
    public static function providerPaths(): iterable
    {
        $source = 'function foo($a,$b){return $a.$b;}function bar(){foo("a","b");foo("c","d");}function other(){foo("x","y");}';
        yield 'direct correlated calls' => [$source, 'bar -> foo#return', ['ab','cd'], false];
        yield 'formal parameter' => [$source, 'bar -> foo#parameter(a)', ['a','c'], false];
        yield 'direct through ellipsis' => [$source, 'bar -> ... -> foo#return', ['ab','cd'], false];
        yield 'case and leading namespace' => [$source, '\\BAR -> \\FOO#return', ['ab','cd'], false];
        $source = 'function foo($x){return helper($x);}function helper($x){return $x+1;}function bar($x){foo($x);}function entry(){bar(11);}function other(){bar(22);}';
        yield 'ancestor inputs' => [$source, 'entry -> bar -> foo#return', [12], false];
        yield 'ellipsis inputs' => [$source, 'entry -> ... -> foo#return', [12], false];
        yield 'mixed edges' => [$source, 'entry -> ... -> bar -> foo#return', [12], false];
        yield 'open outer prefix' => [$source, 'bar -> foo#return', [12,23], false];
        yield 'not a direct parent' => [$source, 'entry -> foo#return', [], false];
        yield 'no reverse path' => [$source, 'bar -> entry#return', [], false];
        $source = 'function foo($x){return $x?11:27;}function bar(){foo(true);}function other(){foo(false);}';
        yield 'default keeps conditional alternatives' => [$source, 'bar -> foo#return', [11,27], false];
        yield 'configured pruning uses selected input' => [$source, 'bar -> foo#return', [11], true];
        yield 'other caller has its own input' => [$source, 'other -> foo#return', [27], true];
        $source = 'function foo(){return 7;}function bar(){if(false){foo();}}';
        yield 'default keeps call site branch' => [$source, 'bar -> foo#return', [7], false];
        yield 'configured pruning excludes call site' => [$source, 'bar -> foo#return', [], true];
        yield 'constant still requires matching path' => ['function foo(){return 7;}function bar(){return 2;}', 'bar -> foo#return', [], false];
        yield 'unused arguments and statements' => ['function foo($unused,$x){return $x;}function bar(){expensive();foo(expensive(),42);return expensive();}function other(){foo(1,expensive());}', 'bar -> foo#return', [42], false];
        yield 'named and default arguments' => ['function foo($x=4,$y=2){return $x+$y;}function bar(){foo(y:9);}', 'bar -> foo#return', [13], false];
        yield 'variadic arguments' => ['function foo(...$xs){return $xs[1];}function bar(){foo(1,2,3);}', 'bar -> foo#return', [2], false];
        yield 'instance method receiver' => ['class A{public $value;function foo(){return $this->value;}function __construct($x){$this->value=$x;}}function bar(){(new A(7))->foo();}', 'bar -> A::foo#return', [7], false];
        yield 'static methods' => ['class A{static function foo($x){return $x;}static function bar(){self::foo(5);}}', 'A::bar -> A::foo#return', [5], false];
        yield 'dispatch excludes different class' => ['class A{function foo(){return 1;}}class B{function foo(){return 2;}}function bar(){(new B())->foo();}', 'bar -> A::foo#return', [], false];
        yield 'dynamic function in direct caller' => ['function foo($x){return $x;}function bar(){$f="foo";$f(7);}', 'bar -> foo#return', [7], false];
        yield 'dynamic function under ancestor' => ['function foo($x){return $x;}function bar($x){$f="foo";$f($x);}function entry(){bar(8);}', 'entry -> ... -> foo#return', [8], false];
        yield 'unreachable recursive component' => ['function foo($x){return $x;}function recur($x){recur($x+1);foo($x);}function entry(){return 7;}', 'entry -> ... -> foo#return', [], false];
        $prefix = 'function bar(){if(false){';
        yield 'excluded call argument stays unopened' => [$prefix . 'unknown(expensive());}}', 'bar -> fixture.php:1:' . (strlen('<?php ' . $prefix) + 1) . '#argument(0)', [], true];
        $prefix = 'function foo($x){return $x;}function bar($x){';
        $source = $prefix . 'foo($x);}function entry(){bar(6);}function other(){bar(9);}';
        $site = 'fixture.php:1:' . (strlen('<?php ' . $prefix) + 1) . '#argument(0)';
        yield 'call site direct parent' => [$source, 'entry -> bar -> ' . $site, [6], false];
        yield 'call site ancestor' => [$source, 'entry -> ... -> ' . $site, [6], false];
        yield 'call site mismatch' => [$source, 'entry -> ' . $site, [], false];
    }

    /**
     * @param list<scalar|null> $expected
     */
    #[DataProvider('providerPaths')]
    public function testDeriveUsesOnlyMatchingInvocationContexts(string $source, string $query, array $expected, bool $pruning): void
    {
        $opened = 0;
        $evaluators = [...($pruning ? BranchEvaluators::truth() : []), new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(99);
        })];
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $program = new Index('fixture', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $runner = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment'));
        $result = $runner->derive((new SelectorResolution($program))->resolve(Query::parse($query)));
        self::assertEqualsCanonicalizing($expected, array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result)));
        self::assertSame(0, $opened);
        self::assertFalse($result->interrupted);
    }
    public function testRepeatedQueriesKeepPathIdentityAndCallEvidenceSeparate(): void
    {
        $source = 'function foo($x){return $x;}function bar($x){foo($x);}function entry(){bar(7);}function other(){bar(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $program = new Index('fixture', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target);
        $runner = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment'));
        $selection = new SelectorResolution($program);
        $first = $runner->derive($selection->resolve(Query::parse('entry -> bar -> foo#return')));
        $second = $runner->derive($selection->resolve(Query::parse('other -> bar -> foo#return')));
        $repeat = $runner->derive($selection->resolve(Query::parse('entry -> bar -> foo#return')));
        self::assertSame(7, $first[0]->result);
        self::assertSame(9, $second[0]->result);
        self::assertEquals($first->reference, $repeat->reference);
        self::assertNotEquals($first->reference, $second->reference);
        self::assertTrue($first[0]->evidence[0]->matchesCaller('entry'));
        self::assertFalse($first[0]->evidence[0]->matchesCaller('other'));
    }

    public function testReplacesTheTargetWithItsSelectedLazyInputs(): void
    {
        $source = 'function foo($x,$unused){return expensive();}function bar(){foo(7,expensive());}function other(){foo(9,expensive());}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $program = new Index('fixture', [$tree]);
        $target = new TargetProfile();
        $opened = 0;
        $evaluators = [new MicroEvaluator('foo', '1', EvaluationOperation::Function, 'foo', static fn (\Deriver\Deriver\Evaluation $evaluation): Term => $evaluation->input(0)), new MicroEvaluator('expensive', '1', EvaluationOperation::Function, 'expensive', static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(99);
        })];
        $settings = new EvaluatorConfig($target, evaluators: array_map((new MicroEvaluatorConversion())->convert(...), $evaluators));
        $runner = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment'));
        $result = $runner->derive((new SelectorResolution($program))->resolve(Query::parse('bar -> foo#return')));
        self::assertSame(7, $result[0]->result);
        self::assertSame(0, $opened);
        self::assertSame(2, $program->graphCount());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUnresolvedDispatch(): iterable
    {
        yield 'function name' => ['function foo(){return 7;}function bar($f){$f();}', 'bar -> foo#return'];
        yield 'receiver class' => ['class A{function foo(){return 7;}}function bar($object){$object->foo();}', 'bar -> A::foo#return'];
        yield 'static class' => ['class A{static function foo(){return 7;}}function bar($class){$class::foo();}', 'bar -> A::foo#return'];
        yield 'method name' => ['class A{function foo(){return 7;}}function bar($name){(new A())->$name();}', 'bar -> A::foo#return'];
        yield 'static method name' => ['class A{static function foo(){return 7;}}function bar($name){A::$name();}', 'bar -> A::foo#return'];
    }

    #[DataProvider('providerUnresolvedDispatch')]
    public function testUnresolvedDispatchRetainsThePathBoundary(string $source, string $query): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $program = new Index('fixture', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target);
        $runner = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment'));
        $result = $runner->derive((new SelectorResolution($program))->resolve(Query::parse($query)));
        self::assertCount(1, $result);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('UNRESOLVED_DISPATCH', $result[0]->result->attributes['reason']);
    }

    public function testDepthBudgetRetainsAnUnfinishedPathInsteadOfUnrestrictedCandidates(): void
    {
        $source = 'function foo(){return 7;}function bar(){foo();}function entry(){bar();}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $program = new Index('fixture', [$tree]);
        $target = new TargetProfile();
        $settings = new EvaluatorConfig($target);
        $runner = new CandidateQuery(new DefinitionIndex($program), $settings, new Evaluators($target), new Snapshot('fixture', [], [], $target, 'environment'));
        $query = new \Deriver\Deriver\Evaluator\Request\ReturnQuery('foo', \Deriver\Deriver\Evaluator\Request\QueryScope::through(new \Deriver\Deriver\Evaluator\Request\CallPath(['entry','bar'])), new \Deriver\Deriver\Evaluator\Request\Budget(maxDepth: 1));
        $result = $runner->derive($query);
        self::assertCount(1, $result);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('DEPTH_LIMIT', $result[0]->result->attributes['reason']);
    }

}

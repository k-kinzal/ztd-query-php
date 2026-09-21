<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\CallEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\TextPattern;

#[CoversClass(CallEvaluator::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(Environment::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
final class CallEvaluatorTest extends TestCase
{
    public function testTheCallMethodsAreReachedDirectly(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function q(): string { return "SELECT 1"; }'
            . ' function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s->execute([1]); PDO::query("x"); q(); new PDO("sqlite::memory:"); }',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Analysis\SinkMatcher((new PdoExtension())->sinks(), $index),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, (new PdoExtension())->sinks()))->evaluatorFor();
        $scope = new FunctionScope('t.php', 'f', null);
        $environment = new Environment(['d' => Domain::of(new ObjectTerm('PDO'))]);
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\CallLike::class);

        $results = array_map(
            static fn (\PhpParser\Node\Expr\CallLike $call): string => $evaluator
                ->evaluate($call, $environment, $scope, $expressions)
                ->type()
                ->display(),
            $calls,
        );

        self::assertSame(['PDOStatement', 'mixed', 'mixed', 'string', 'PDO'], $results);
    }

    public function testArgumentsIsCalledWithTheCallItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php f("a", 2);');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $call = $statement->expr;
        self::assertInstanceOf(FuncCall::class, $call);

        $recorder = new StatementRecorder();
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Analysis\SinkMatcher([], new ProgramIndex()),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $arguments = $evaluator->arguments($call, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame(['a', 2], array_map(
            static fn (Domain $domain): string|int|float|bool|null => $domain->soleLiteral()?->value,
            $arguments,
        ));
    }

    public function testSiteKeyOfNamesTheCallAndTheDatabaseCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $statement = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $statement);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Analysis\SinkMatcher([], new ProgramIndex()),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );

        self::assertSame(
            't.php:' . $statement->getStartFilePos() . ':pdo.query',
            $evaluator->siteKeyOf($statement, new FunctionScope('t.php', 'f', null), 'pdo.query'),
        );
    }

    public function testApplySinkHandsBackAHandleNamingThePreparingCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->prepare("SELECT ?"); }');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Analysis\SinkMatcher([], new ProgramIndex()),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );
        $sink = (new PdoExtension())->sinks()[2];
        $scope = new FunctionScope('t.php', 'f', null);

        $object = $evaluator->applySink($sink, $call, [Domain::literal('SELECT ?')], $scope)->soleObject();

        self::assertNotNull($object);
        self::assertSame('PDOStatement', $object->className);
        self::assertSame($evaluator->siteKeyOf($call, $scope, $sink->id), $object->statementId);
    }

    public function testEvaluateRecordsAQueryCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $recorder = new StatementRecorder();
        (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertCount(1, $records);
        self::assertSame('SELECT 1', $records[0]->pattern->text());
        self::assertSame([], $recorder->records());
    }

    public function testEvaluateInstantiationGivesTheDriverItsType(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $d = new PDO("sqlite::memory:"); $d->query("SELECT 1");');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertCount(1, $records);
    }

    public function testEvaluateInstantiationResolvesSelf(): void
    {
        $evaluator = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $recorded = $evaluator->evaluate(new New_(new Name('self')), new Environment(), new FunctionScope('t.php', 'C::m', 'C'));
        self::assertSame('C', $recorded->soleObject()?->className);
    }

    public function testEvaluateFollowsAMethodOfTheAnalyzedSource(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class R { public function __construct(private PDO $d) {}'
            . ' public function sql(): string { return "SELECT 1"; }'
            . ' public function run(): void { $this->d->query($this->sql()); } }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        $texts = array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records);
        self::assertContains('SELECT 1', $texts);
    }

    public function testEvaluateFollowsAStaticCallOfTheAnalyzedSource(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Q { public static function sql(): string { return "SELECT 2"; } }'
            . ' function f(PDO $d): void { $d->query(Q::sql()); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        $texts = array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records);
        self::assertContains('SELECT 2', $texts);
    }

    public function testEvaluateFollowsAFreeFunctionOfTheAnalyzedSource(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function sql(): string { return "SELECT 3"; } function f(PDO $d): void { $d->query(sql()); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        $texts = array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records);
        self::assertContains('SELECT 3', $texts);
    }

    public function testEvaluateReadsExternalInputThroughAFunction(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT " . getenv("X")); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame(\SqlCatalog\Text\Origin::External, $records[0]->pattern->holes()[0]->origin);
    }

    public function testArgumentsAreEvaluatedInOrder(): void
    {
        $evaluator = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $call = new FuncCall(new Name('sprintf'), [
            new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_('%s')),
            new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_('a')),
        ]);
        $result = $evaluator->evaluate($call, new Environment(), new FunctionScope('t.php'));
        self::assertSame('a', $result->soleLiteral()?->value);
    }

    public function testEvaluateMethodGivesUpWhenTheNameIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d, string $m): void { $d->$m("SELECT 1"); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame([], $records);
    }

    public function testEvaluateStaticGivesUpWhenTheClassIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(string $c): void { $c::run(); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame([], $records);
    }

    public function testEvaluateFunctionGivesUpWhenTheNameIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(callable $c): void { $c("SELECT 1"); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame([], $records);
    }

    public function testApplySinkBindsWhatAQueryCallCarries(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s->bindValue(1, "a"); $s->execute(); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame('a', $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testApplyPrepareHandsBackAHandleLaterCallsFind(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s->execute([7]); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame(7, $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testRecordStatementsIgnoresACallWithoutItsStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query(); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame([], $records);
    }

    public function testBindValuesAttachesVariadicArguments(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(mysqli $m): void { $s = $m->prepare("SELECT ?"); $s->bind_param("s", "a"); }',
        );
        $sinks = ExtensionRegistry::withBuiltins()->sinksOf(['mysqli']);
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), $sinks))->analyze([$file]);
        self::assertSame('a', $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testFollowReadsTheSameCallOnceForTheSameArguments(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function sql(): string { return "SELECT 1"; }'
            . ' function f(PDO $d): void { $d->query(sql()); $d->query(sql()); }',
        );

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertSame(['SELECT 1', 'SELECT 1'], array_map(
            static fn (QueryRecord $record): ?string => $record->pattern->text(),
            $records,
        ));
    }

    public function testFollowStopsAtRecursion(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function sql(): string { return sql(); } function f(PDO $d): void { $d->query(sql()); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertFalse($records[0]->pattern->isExact());
    }

    public function testFollowStopsAtTheDepthLimit(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function a(): string { return b(); } function b(): string { return c(); }'
            . ' function c(): string { return d(); } function d(): string { return e(); }'
            . ' function e(): string { return "SELECT 1"; } function f(PDO $d): void { $d->query(a()); }',
        );
        $sinks = (new PdoExtension())->sinks();
        $budget = new \SqlCatalog\Analysis\EvaluationBudget(200000, 2);
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), $sinks, $budget))->analyze([$file]);
        $texts = array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records);
        self::assertNotContains('SELECT 1', $texts);
    }

    public function testSiteOfNamesWhereTheCallIsWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', "<?php\nfunction f(PDO \$d): void { \$d->query('SELECT 1'); }");
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertSame(2, $records[0]->site->line);
        self::assertSame('f', $records[0]->site->function);
        self::assertSame('pdo.query', $records[0]->site->sink);
    }

    public function testSiteKeyOfTellsTwoCallsOnOneLineApart(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); $d->query("SELECT 2"); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        self::assertNotSame($records[0]->siteKey, $records[1]->siteKey);
    }

    public function testCallKeyOfTellsOneCallApartFromEveryOther(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($d): void { $d->q(); $d->q(); }');
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Analysis\SinkMatcher([], $index),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );
        $scope = new FunctionScope('t.php', 'f', null);

        self::assertNotSame(
            $evaluator->callKeyOf($calls[0], $scope),
            $evaluator->callKeyOf($calls[1], $scope),
        );
        self::assertStringStartsWith('t.php:', $evaluator->callKeyOf($calls[0], $scope));
    }

    public function testDispatchResolvesAcrossTheImplementationsTheSourceDeclares(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php abstract class T { public function __construct(protected PDO $d) {}'
            . ' abstract public function table(): string;'
            . ' public function rows(): void { $this->d->query("SELECT * FROM " . $this->table()); } }'
            . ' class U extends T { public function table(): string { return "users"; } }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);
        $texts = array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records);
        self::assertContains('SELECT * FROM users', $texts);
    }

    public function testIsOwnReceiverTellsACallOnItselfApartFromOneOnSomethingElse(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php class C { function f($d) { $this->g(); $d->g(); } }');
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Analysis\SinkMatcher([], $index),
            new \SqlCatalog\Analysis\BuiltinCallModel(),
            new \SqlCatalog\Analysis\ExternalInput(),
            new \SqlCatalog\Php\NodeText(),
        );

        self::assertTrue($evaluator->isOwnReceiver($calls[0]));
        self::assertFalse($evaluator->isOwnReceiver($calls[1]));
    }
}

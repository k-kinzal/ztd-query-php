<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\CallEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Analysis\QueryRecord;
use SqlCatalog\Core\Analysis\StatementRecorder;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Extension\Pdo\PdoExtension;

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
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderCalls::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\QueryState::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
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
            new \SqlCatalog\Core\Analysis\SinkMatcher((new PdoExtension())->sinks(), $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
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
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
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
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
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
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
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
        self::assertSame(\SqlCatalog\Core\Text\Origin::External, $records[0]->pattern->holes()[0]->origin);
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
        $sinks = \SqlCatalog\Facade\Builtins::extensions()->sinksOf(['mysqli']);
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
        $budget = new \SqlCatalog\Core\Analysis\EvaluationBudget(200000, 2);
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
            new \SqlCatalog\Core\Analysis\SinkMatcher([], $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
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
            new \SqlCatalog\Core\Analysis\SinkMatcher([], $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );

        self::assertTrue($evaluator->isOwnReceiver($calls[0]));
        self::assertFalse($evaluator->isOwnReceiver($calls[1]));
    }

    public function testEvaluateOfAFirstClassCallableIsAClosure(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php strlen(...);');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        self::assertSame('Closure', $evaluator->evaluate($call, new Environment(), new FunctionScope('t.php'), $expressions)->soleObject()?->className);
    }

    public function testEvaluateInstantiationNamesTheClassItCreates(): void
    {
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $scope = new FunctionScope('t.php', 'C::m', 'C');

        self::assertSame('App\\Db', $evaluator->evaluateInstantiation(new New_(new Name('App\\Db')), $scope)->soleObject()?->className);
        self::assertSame('C', $evaluator->evaluateInstantiation(new New_(new Name('SELF')), $scope)->soleObject()?->className);
        self::assertSame('object', $evaluator->evaluateInstantiation(new New_(new \PhpParser\Node\Expr\Variable('class')), $scope)->type()->display());
    }

    public function testEvaluateMethodGivesUpOnAMethodWhoseNameIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $d->$m();');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $result = $evaluator->evaluateMethod($call, [], new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame('$d->{$m}()', $result->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateMethodFollowsIntoAClassNoExtensionModels(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { function sql(): string { return "SELECT 1"; } } $r->sql();');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $call);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Core\Analysis\SinkMatcher((new PdoExtension())->sinks(), $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, (new PdoExtension())->sinks()))->evaluatorFor();
        $environment = new Environment(['r' => Domain::of(new ObjectTerm('R'))]);

        $result = $evaluator->evaluateMethod($call, [], $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame('R::sql()', $result->patterns()[0]->holes()[0]->expression);
        self::assertSame('string', $result->type()->display());
    }

    public function testEvaluateMethodFollowsIntoAModelledClassOnlyFromItsOwnBody(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class ZtdPdo extends PDO { function sql(): string { return "SELECT 1"; } function run(): void { $this->sql(); } } $z->sql();',
        );
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Core\Analysis\SinkMatcher((new PdoExtension())->sinks(), $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, (new PdoExtension())->sinks()))->evaluatorFor();
        $environment = new Environment(['z' => Domain::of(new ObjectTerm('ZtdPdo'))]);

        $inside = $evaluator->evaluateMethod($calls[0], [], $environment, new FunctionScope('t.php', 'ZtdPdo::run', 'ZtdPdo'), $expressions);
        $outside = $evaluator->evaluateMethod($calls[1], [], $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame('ZtdPdo::sql()', $inside->patterns()[0]->holes()[0]->expression);
        self::assertSame('$z->sql()', $outside->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateMethodFollowsAMethodDeclaredWithoutABody(): void
    {
        $method = new \SqlCatalog\Core\Php\MethodShape('R', 'sql', [], \SqlCatalog\Core\Type\TypeShape::of(['string']));
        $index = new ProgramIndex(['r' => new \SqlCatalog\Core\Php\ClassShape('R', null, [], [], false, [], [], [], ['sql' => $method])]);
        $file = (new SourceParser())->parse('t.php', '<?php $r->sql();');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $call);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Core\Analysis\SinkMatcher([], $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $environment = new Environment(['r' => Domain::of(new ObjectTerm('R'))]);

        $result = $evaluator->evaluateMethod($call, [], $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame('R::sql()', $result->patterns()[0]->holes()[0]->expression);
    }

    public function testDispatchReadsTheImplementationsTheSourceDeclares(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php abstract class T { abstract public function table(): string; } class U extends T { public function table(): string { return "users"; } }',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Core\Analysis\SinkMatcher([], $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        self::assertSame('U::table()', $evaluator->dispatch('T', 'table', [], $scope, $expressions)?->patterns()[0]->holes()[0]->expression);
        self::assertNull($evaluator->dispatch('T', 'missing', [], $scope, $expressions));
    }

    public function testEvaluateStaticGivesUpWhenTheClassOrTheNameIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $c::run(); C::$m();');
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\StaticCall::class);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        self::assertSame(
            ['$c::run()', '\\C::$m()'],
            array_map(
                static fn (\PhpParser\Node\Expr\StaticCall $call): ?string => $evaluator->evaluateStatic($call, [], new FunctionScope('t.php'), $expressions)->patterns()[0]->holes()[0]->expression,
                $calls,
            ),
        );
    }

    public function testEvaluateStaticResolvesTheSelfKeywordsToTheEnclosingClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Q { static function sql(): string { return "SELECT 1"; } } self::sql(); SELF::sql(); Q::sql();',
        );
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\StaticCall::class);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator(
            $index,
            new \SqlCatalog\Core\Analysis\SinkMatcher([], $index),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter($index, []))->evaluatorFor();

        self::assertSame('Q::sql()', $evaluator->evaluateStatic($calls[0], [], new FunctionScope('t.php', 'Q::m', 'Q'), $expressions)->patterns()[0]->holes()[0]->expression);
        self::assertSame('Q::sql()', $evaluator->evaluateStatic($calls[1], [], new FunctionScope('t.php', 'Q::m', 'Q'), $expressions)->patterns()[0]->holes()[0]->expression);
        self::assertSame('Q::sql()', $evaluator->evaluateStatic($calls[2], [], new FunctionScope('t.php', 'Other::m', 'Other'), $expressions)->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateStaticHandsBackWhatADatabaseCallHandsBack(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php DB::select("SELECT 1");');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\StaticCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\StaticCall::class, $call);
        $sinks = [new \SqlCatalog\Core\Extension\SinkSpec('db.select', \SqlCatalog\Core\Extension\SinkCallKind::StaticCall, 'DB', 'select', \SqlCatalog\Core\Extension\SinkRole::Query, sqlParameter: 0)];
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher($sinks, new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), $sinks))->evaluatorFor();

        $result = $evaluator->evaluateStatic($call, [Domain::literal('SELECT 1')], new FunctionScope('t.php'), $expressions);

        self::assertSame('db.select', $result->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateFunctionGivesUpOnAFunctionWhoseNameIsNotWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $f("SELECT 1");');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $result = $evaluator->evaluateFunction($call, [Domain::literal('SELECT 1')], new FunctionScope('t.php'), $expressions);

        self::assertSame('$f("SELECT 1")', $result->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateFunctionHandsBackWhatADatabaseCallHandsBack(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php mysqli_query($m, "SELECT 1");');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        $sinks = (new \SqlCatalog\Extension\Mysqli\MysqliExtension())->sinks();
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher($sinks, new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), $sinks))->evaluatorFor();

        $result = $evaluator->evaluateFunction($call, [Domain::unknown(), Domain::literal('SELECT 1')], new FunctionScope('t.php'), $expressions);

        self::assertSame('mysqli.fn.query', $result->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateFunctionNamesTheExternalInputItReads(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php getenv("X");');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $hole = $evaluator->evaluateFunction($call, [Domain::literal('X')], new FunctionScope('t.php'), $expressions)->patterns()[0]->holes()[0];

        self::assertSame(\SqlCatalog\Core\Text\Origin::External, $hole->origin);
        self::assertSame('getenv()', $hole->expression);
    }

    public function testApplySinkHandsBackWhatEachKindOfDatabaseCallHandsBack(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $d->run("a", "b");');
        $call = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\MethodCall::class, $call);
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $scope = new FunctionScope('t.php');
        $arguments = [Domain::literal('a'), Domain::literal('b')];
        $kind = \SqlCatalog\Core\Extension\SinkCallKind::Method;

        $execute = $evaluator->applySink(new \SqlCatalog\Core\Extension\SinkSpec('db.execute', $kind, 'Db', 'run', \SqlCatalog\Core\Extension\SinkRole::Execute), $call, $arguments, $scope);
        $bind = $evaluator->applySink(new \SqlCatalog\Core\Extension\SinkSpec('db.bind', $kind, 'Db', 'run', \SqlCatalog\Core\Extension\SinkRole::Bind), $call, $arguments, $scope);
        $query = $evaluator->applySink(new \SqlCatalog\Core\Extension\SinkSpec('db.query', $kind, 'Db', 'run', \SqlCatalog\Core\Extension\SinkRole::Query, sqlParameter: 0), $call, $arguments, $scope);
        $composeSecond = $evaluator->applySink(new \SqlCatalog\Core\Extension\SinkSpec('db.compose', $kind, 'Db', 'run', \SqlCatalog\Core\Extension\SinkRole::Compose, sqlParameter: 1), $call, $arguments, $scope);
        $composeFirst = $evaluator->applySink(new \SqlCatalog\Core\Extension\SinkSpec('db.compose', $kind, 'Db', 'run', \SqlCatalog\Core\Extension\SinkRole::Compose), $call, $arguments, $scope);

        self::assertSame('bool', $execute->type()->display());
        self::assertSame('db.execute', $execute->patterns()[0]->holes()[0]->expression);
        self::assertSame('bool', $bind->type()->display());
        self::assertSame('db.query', $query->patterns()[0]->holes()[0]->expression);
        self::assertSame('b', $composeSecond->soleLiteral()?->value);
        self::assertSame('a', $composeFirst->soleLiteral()?->value);
    }

    public function testFollowWithoutReturnsToReadNamesTheCallee(): void
    {
        $evaluator = new CallEvaluator(
            new ProgramIndex(),
            new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()),
            \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(),
            new \SqlCatalog\Core\Analysis\ExternalInput(),
            new \SqlCatalog\Core\Php\NodeText(),
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $scope = new FunctionScope('t.php');
        $string = \SqlCatalog\Core\Type\TypeShape::of(['string']);

        $function = $evaluator->follow(new \SqlCatalog\Core\Php\FunctionShape('sql', [], $string), [], $scope, $expressions);
        $method = $evaluator->follow(new \SqlCatalog\Core\Php\MethodShape('Q', 'sql', [], $string), [], $scope, $expressions);

        self::assertSame('sql()', $function->patterns()[0]->holes()[0]->expression);
        self::assertSame('Q::sql()', $method->patterns()[0]->holes()[0]->expression);
        self::assertSame('string', $method->type()->display());
    }
    public function testFunctionNameUsesRegisteredNamespaceBeforeGlobalFallback(): void
    {
        $models = \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins();
        $models->register('App\implode', static fn (array $arguments): ?\SqlCatalog\Core\Evaluation\Domain => null);
        $evaluator = new CallEvaluator(new ProgramIndex(), new \SqlCatalog\Core\Analysis\SinkMatcher([], new ProgramIndex()), $models, new \SqlCatalog\Core\Analysis\ExternalInput(), new \SqlCatalog\Core\Php\NodeText());
        $local = new Name('implode', ['namespacedName' => new Name('App\implode')]);
        self::assertSame('App\implode', $evaluator->functionName($local));
        self::assertSame('implode', $evaluator->functionName(new Name('implode')));
    }

    public function testApplyEffectsKeepsKnownByValueArgumentsAndOpensReferences(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function update($keep, &$change) {} update($a, $b);');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new CallEvaluator($index, new \SqlCatalog\Core\Analysis\SinkMatcher([], $index), \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(), new \SqlCatalog\Core\Analysis\ExternalInput(), new \SqlCatalog\Core\Php\NodeText());
        $statement = $file->statements[1];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        self::assertInstanceOf(FuncCall::class, $statement->expr);
        $environment = new Environment(['a' => Domain::literal('a')]);
        $environment->markAbsent('b');
        $evaluator->applyEffects($statement->expr, $environment);
        self::assertSame('a', $environment->read('a')->soleLiteral()?->value);
        self::assertNull($environment->read('b')->soleLiteral());
    }


    /**
     * @param array<string, string> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCallWrites')]
    public function testEvaluateAppliesOnlyPossibleCallWrites(string $source, array $expected): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function known($a, &$b) {} ' . $source . ';');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $sinks = (new PdoExtension())->sinks();
        $evaluator = new CallEvaluator($index, new \SqlCatalog\Core\Analysis\SinkMatcher($sinks, $index), \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(), new \SqlCatalog\Core\Analysis\ExternalInput(), new \SqlCatalog\Core\Php\NodeText());
        $statement = $file->statements[1];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        self::assertInstanceOf(\PhpParser\Node\Expr\CallLike::class, $statement->expr);
        $environment = new Environment(['a' => Domain::literal('a'), 'b' => Domain::literal('b'), 'd' => Domain::of(new ObjectTerm('PDO'))]);
        $evaluator->evaluate($statement->expr, $environment, new FunctionScope('a.php'), (new Interpreter($index, $sinks))->evaluatorFor());
        self::assertSame($expected, array_map(static fn (string $name): string => $environment->read($name)->signature(), ['a' => 'a', 'b' => 'b']));
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function providerCallWrites(): array
    {
        $kept = ['a' => 'literal:string:a', 'b' => 'literal:string:b'];
        $changed = ['a' => 'literal:string:a', 'b' => 'opaque:mixed:unresolved'];
        return [
            'unknown' => ['unknown($b)', $changed],
            'declared' => ['known($a, $b)', $changed],
            'builtin' => ['str_replace($a, $a, $a, $b)', $changed],
            'builtin without reference output' => ['str_replace($a, $a, $b)', $kept],
            'read only builtin' => ['strtolower($a)', $kept],
            'pdo sink' => ['$d->prepare($a)', $kept],
            'unknown method' => ['$d->other($b)', $changed],
            'dynamic method' => ['$d->$a($b)', $changed],
            'unknown static' => ['Other::change($b)', $changed],
            'unknown constructor' => ['new Other($b)', $changed],
        ];
    }

    public function testEvaluateRetainsReferenceWritesForAModeledNamespacedFunction(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php namespace App; function strtolower(&$value) { $value = "changed"; } strtolower($a);');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $models = \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins();
        $models->register('App\strtolower', static fn (array $arguments): Domain => Domain::literal('modeled'));
        $evaluator = new CallEvaluator($index, new \SqlCatalog\Core\Analysis\SinkMatcher([], $index), $models, new \SqlCatalog\Core\Analysis\ExternalInput(), new \SqlCatalog\Core\Php\NodeText());
        $namespace = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Namespace_::class, $namespace);
        $statement = $namespace->stmts[1];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        self::assertInstanceOf(FuncCall::class, $statement->expr);
        $environment = new Environment(['a' => Domain::literal('before')]);
        $result = $evaluator->evaluate($statement->expr, $environment, new FunctionScope('a.php'), (new Interpreter($index, []))->evaluatorFor());
        self::assertSame('modeled', $result->soleLiteral()?->value);
        self::assertNull($environment->read('a')->soleLiteral());
    }


    public function testInvalidateEscapesOpensAliasedObjectState(): void
    {
        $index = new ProgramIndex();
        $object = new ObjectTerm('DemoObject', identity: 'demo:1', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        $env = new Environment(['q' => Domain::of($object), 'alias' => Domain::of($object)]);
        $calls = new CallEvaluator($index, new \SqlCatalog\Core\Analysis\SinkMatcher([], $index), \SqlCatalog\Core\Analysis\FunctionModel\Registry::withBuiltins(), new \SqlCatalog\Core\Analysis\ExternalInput(), new \SqlCatalog\Core\Php\NodeText());
        $call = new FuncCall(new Name('customize'), [new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\Variable('q'))]);
        $calls->invalidateEscapes($call, $env);
        $read = $env->read('alias')->soleObject();
        self::assertNotNull($read);
        self::assertNull($read->state);
    }
}

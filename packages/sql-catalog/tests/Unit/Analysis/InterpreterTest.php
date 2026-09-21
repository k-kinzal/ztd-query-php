<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Analysis\SinkMatcher;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Analysis\ValueBinder;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\Origin;

#[CoversClass(Interpreter::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
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
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BranchArms::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(SinkMatcher::class)]
#[UsesClass(ValueBinder::class)]
#[UsesClass(\SqlCatalog\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
final class InterpreterTest extends TestCase
{
    public function testAnalyzeReadsEveryStatementFromTheCallThatIssuesIt(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d, bool $admin): void { if ($admin) { $t = "admins"; $c = "admin_id"; } else { $t = "users"; $c = "user_id"; } $d->query("SELECT $c FROM $t"); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertSame(
            ['SELECT admin_id FROM admins', 'SELECT user_id FROM users'],
            array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records),
        );
    }

    public function testAnalyzeBindsWhatAnExecuteBindsToTheStatementItsHandleCameFrom(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $s = $d->prepare("SELECT * FROM u WHERE id = ?"); $s->execute([7]); }',
        );
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertCount(1, $records);
        self::assertSame(7, $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testEvaluatorForWiresAnEvaluatorOverTheFiles(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function t(): string { return "users"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $call = new Expr\FuncCall(new \PhpParser\Node\Name('t'));

        $value = (new Interpreter($index, []))->evaluatorFor([$file])
            ->evaluate($call, new \SqlCatalog\Evaluation\Environment(), new FunctionScope('t.php'));

        self::assertSame('users', $value->soleLiteral()?->value);
    }

    public function testDeriverForWiresADeriverOverTheFiles(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $t = "users"; $x = $t;');
        $statement = $file->statements[1];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\Assign::class, $statement->expr);

        $solutions = (new Interpreter(new ProgramIndex(), []))->deriverFor([$file])->solve($statement, [$statement->expr->expr]);

        self::assertSame('users', $solutions[0]->values[0]->soleLiteral()?->value);
    }

    public function testVisitRecordsAStatementAndHandsBackNothingToBindLater(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        $later = $interpreter->visit(
            $call,
            $interpreter->deriverFor([$file]),
            new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex()),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertSame([], $later);
        self::assertSame('SELECT 1', $recorder->records()[0]->pattern->text());
    }

    public function testVisitHandsBackWhatAnExecuteBinds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s->execute([1]); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\MethodCall::class);
        $recorder = new StatementRecorder();

        $later = $interpreter->visit(
            $calls[1],
            $interpreter->deriverFor([$file]),
            new SinkMatcher((new PdoExtension())->sinks(), new ProgramIndex()),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertCount(1, $later);
        self::assertSame('pdo.statement.execute', $later[0][0]->id);
    }

    public function testSinkOfTellsADatabaseCallFromAnotherCallOfTheSameName(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Q { function query(string $s): void {} } function f(PDO $d, Q $q): void { $d->query("SELECT 1"); $q->query("x"); }',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $interpreter = new Interpreter($index, (new PdoExtension())->sinks());
        $deriver = $interpreter->deriverFor([$file]);
        $matcher = new SinkMatcher((new PdoExtension())->sinks(), $index);
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\MethodCall::class);

        self::assertSame('pdo.query', $interpreter->sinkOf($calls[0], $deriver, $matcher, $deriver->scopeOf($calls[0]))?->id);
        self::assertNull($interpreter->sinkOf($calls[1], $deriver, $matcher, $deriver->scopeOf($calls[1])));
    }

    public function testReceiverOfWorksOutWhatAMethodIsCalledOn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(): void { global $wpdb; $wpdb->query("SELECT 1"); }');
        $interpreter = new Interpreter(new ProgramIndex(), [], null, new DeclaredGlobals(['wpdb' => 'wpdb']));
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);

        self::assertSame(['wpdb'], $interpreter->receiverOf($call, $interpreter->deriverFor([$file]))->type()->names);
    }

    public function testUnidentifiedIsTrueOnlyForATextCarryingCallOnSomethingUnknown(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($x, PDO $d): void { $x->query("SELECT 1"); $d->query("SELECT 1"); $x->execute(); }');
        $sinks = (new PdoExtension())->sinks();
        $interpreter = new Interpreter(new ProgramIndex(), $sinks);
        $deriver = $interpreter->deriverFor([$file]);
        $matcher = new SinkMatcher($sinks, new ProgramIndex());
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\MethodCall::class);

        self::assertTrue($interpreter->unidentified($calls[0], $deriver, $matcher));
        self::assertFalse($interpreter->unidentified($calls[1], $deriver, $matcher));
        self::assertFalse($interpreter->unidentified($calls[2], $deriver, $matcher));
    }

    public function testArgumentsOfListsTheExpressionsACallPasses(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php f("a", $b); g(...);');
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class);
        $interpreter = new Interpreter(new ProgramIndex(), []);

        self::assertCount(2, $interpreter->argumentsOf($calls[0]));
        self::assertSame([], $interpreter->argumentsOf($calls[1]));
    }

    public function testRecordStatementsRecordsACallNothingCouldBeReadFromAsUnread(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();
        $sink = (new PdoExtension())->sinks()[0];

        (new Interpreter(new ProgramIndex(), []))->recordStatements(
            $call,
            $sink,
            [],
            new FunctionScope('t.php', 'f'),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertSame(Origin::Unreached, $recorder->records()[0]->pattern->holes()[0]->origin);
    }

    public function testRecordUnmatchedFilesTheCallUnderItsOwnSink(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $x->query("SELECT 1");');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        (new Interpreter(new ProgramIndex(), []))->recordUnmatched($call, new FunctionScope('t.php'), $recorder);

        self::assertSame(CallSite::UNMATCHED, $recorder->records()[0]->site->sink);
    }

    public function testUnreadQuotesTheCallInItsOnlyGap(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $db->query($sql);');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);

        $pattern = (new Interpreter(new ProgramIndex(), []))->unread($call);

        self::assertSame('$db->query($sql)', $pattern->holes()[0]->expression);
    }

    public function testAnalyzeWithATinyBudgetStillReportsTheCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $a = 1; $b = 2; $d->query("SELECT 1"); }');
        $sinks = ExtensionRegistry::withBuiltins()->sinksOf(['pdo']);

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), $sinks, new EvaluationBudget(1)))->analyze([$file]);

        self::assertCount(1, $records);
    }
}

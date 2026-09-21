<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Solution;
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
#[UsesClass(Solution::class)]
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

    public function testAnalyzeSpendsNoMoreThanTheBudgetItIsGiven(): void
    {
        $source = '<?php function f(PDO $d): void { $a = "a"; $b = $a . "b"; $c = $b . "c"; $e = $c . "e"; $g = $e . "g"; $d->query("SELECT " . $g); }';
        $file = (new SourceParser())->parse('t.php', $source);
        $index = (new ProgramIndexBuilder())->build([$file]);

        $tight = (new Interpreter($index, (new PdoExtension())->sinks(), new EvaluationBudget(5)))->analyze([$file]);
        $ample = (new Interpreter($index, (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertFalse($tight[0]->pattern->isExact());
        self::assertSame('SELECT abceg', $ample[0]->pattern->text());
    }

    public function testAnalyzeGivesEveryCallTheWholeBudget(): void
    {
        $body = '(PDO $d): void { $a = "a"; $b = $a . "b"; $c = $b . "c"; $e = $c . "e"; $g = $e . "g"; $d->query("SELECT " . $g); }';
        $file = (new SourceParser())->parse('t.php', '<?php function f' . $body . ' function h' . $body);

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), new EvaluationBudget(10)))->analyze([$file]);

        self::assertSame(['SELECT abceg', 'SELECT abceg'], array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $records));
        self::assertSame(['pdo.query', 'pdo.query'], array_map(static fn (QueryRecord $record): string => $record->site->sink, $records));
    }

    public function testAnalyzeKeepsWhatEveryCallBinds(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $s = $d->prepare("SELECT :a, :b"); $s->bindValue(":a", 1); $s->bindValue(":b", 2); $s->execute(); }',
        );

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertSame(['a' => 1, 'b' => 2], array_map(
            static fn (\SqlCatalog\Evaluation\Domain $value): string|int|float|bool|null => $value->soleLiteral()?->value,
            $records[0]->named(),
        ));
    }

    public function testAnalyzeBindsWhatANullsafeExecuteBinds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $s = $d->prepare("SELECT ?"); $s?->execute([7]); }');

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze([$file]);

        self::assertSame(7, $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testAnalyzeBindsWhatAQueryCallCarriesAlongsideItsStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(mysqli $m): void { $m->execute_query("SELECT ?", [7]); }');

        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new \SqlCatalog\Extension\MysqliExtension())->sinks()))->analyze([$file]);

        self::assertSame('SELECT ?', $records[0]->pattern->text());
        self::assertSame(7, $records[0]->positional()[0]->soleLiteral()?->value);
    }

    public function testAnalyzeRecordsNothingAtACallThatOnlyComposesAStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(): void { global $wpdb; $wpdb->query($wpdb->prepare("SELECT %d", 1)); }');
        $interpreter = new Interpreter(
            (new ProgramIndexBuilder())->build([$file]),
            ExtensionRegistry::withBuiltins()->sinksOf(['pdo', 'wordpress']),
            null,
            new DeclaredGlobals(['wpdb' => 'wpdb']),
        );

        $records = $interpreter->analyze([$file]);

        self::assertSame(['wordpress.query'], array_map(static fn (QueryRecord $record): string => $record->site->sink, $records));
    }

    public function testVisitRecordsNothingForACallWithoutItsStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query(); }');
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
        self::assertSame([], $recorder->records());
    }

    public function testVisitRecordsNothingForADatabaseCallThatTakesNoStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(Db $d): void { $d->ping("SELECT 1"); }');
        $sinks = [new \SqlCatalog\Extension\SinkSpec('db.ping', \SqlCatalog\Extension\SinkCallKind::Method, 'Db', 'ping', \SqlCatalog\Extension\SinkRole::Query)];
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), $sinks);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        $interpreter->visit($call, $interpreter->deriverFor([$file]), new SinkMatcher($sinks, new ProgramIndex()), $recorder, new ValueBinder($recorder));

        self::assertSame([], $recorder->records());
    }

    public function testVisitHandsBackNothingForABindingCallWrittenWithoutAReceiver(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($s): void { stmt_execute($s, [1]); }');
        $sinks = [new \SqlCatalog\Extension\SinkSpec('db.execute', \SqlCatalog\Extension\SinkCallKind::FunctionCall, null, 'stmt_execute', \SqlCatalog\Extension\SinkRole::Execute, valuesParameter: 1)];
        $interpreter = new Interpreter(new ProgramIndex(), $sinks);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $recorder = new StatementRecorder();

        $later = $interpreter->visit($call, $interpreter->deriverFor([$file]), new SinkMatcher($sinks, new ProgramIndex()), $recorder, new ValueBinder($recorder));

        self::assertSame([], $later);
    }

    public function testSinkOfReadsANullsafeCallAndAFunctionCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(mysqli $m, ?mysqli $n, callable $g): void { $n?->query("SELECT 1"); mysqli_query($m, "SELECT 2"); $g("SELECT 3"); }');
        $sinks = (new \SqlCatalog\Extension\MysqliExtension())->sinks();
        $index = (new ProgramIndexBuilder())->build([$file]);
        $interpreter = new Interpreter($index, $sinks);
        $deriver = $interpreter->deriverFor([$file]);
        $matcher = new SinkMatcher($sinks, $index);
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\CallLike::class);

        self::assertSame(
            ['mysqli.query', 'mysqli.fn.query', null],
            array_map(static fn (Expr\CallLike $call): ?string => $interpreter->sinkOf($call, $deriver, $matcher, $deriver->scopeOf($call))?->id, $calls),
        );
    }

    public function testSinkOfResolvesAStaticCallOnTheEnclosingClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class DB { static function all(): void { self::select("a"); SELF::select("b"); static::select("c"); } }'
            . ' class Other { function m(string $c): void { DB::select("d"); $c::select("e"); self::select("f"); } }',
        );
        $sinks = [new \SqlCatalog\Extension\SinkSpec('db.select', \SqlCatalog\Extension\SinkCallKind::StaticCall, 'DB', 'select', \SqlCatalog\Extension\SinkRole::Query, sqlParameter: 0)];
        $index = (new ProgramIndexBuilder())->build([$file]);
        $interpreter = new Interpreter($index, $sinks);
        $deriver = $interpreter->deriverFor([$file]);
        $matcher = new SinkMatcher($sinks, $index);
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\StaticCall::class);

        self::assertSame(
            ['db.select', 'db.select', 'db.select', 'db.select', null, null],
            array_map(static fn (Expr\CallLike $call): ?string => $interpreter->sinkOf($call, $deriver, $matcher, $deriver->scopeOf($call))?->id, $calls),
        );
    }

    public function testReceiverOfJoinsEveryWayTheReceiverCanBe(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class A {} class B {} function f(bool $c): void { if ($c) { $d = new A(); } else { $d = new B(); } $d->query("SELECT 1"); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);

        self::assertSame(['A', 'B'], $interpreter->receiverOf($call, $interpreter->deriverFor([$file]))->type()->classNames());
    }

    public function testUnidentifiedCoversANullsafeCallButNotAFunctionCall(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($x): void { $x?->query("SELECT 1"); query("SELECT 1"); }');
        $sinks = (new PdoExtension())->sinks();
        $interpreter = new Interpreter(new ProgramIndex(), $sinks);
        $deriver = $interpreter->deriverFor([$file]);
        $matcher = new SinkMatcher($sinks, new ProgramIndex());
        $nullsafe = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\NullsafeMethodCall::class);
        self::assertInstanceOf(Expr\NullsafeMethodCall::class, $nullsafe);
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $function);

        self::assertTrue($interpreter->unidentified($nullsafe, $deriver, $matcher));
        self::assertFalse($interpreter->unidentified($function, $deriver, $matcher));
    }

    public function testRecordStatementsSkipsAReadingWithoutTheStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        (new Interpreter(new ProgramIndex(), []))->recordStatements(
            $call,
            (new PdoExtension())->sinks()[0],
            [new Solution([], []), new Solution([\SqlCatalog\Evaluation\Domain::literal('SELECT 2')], [])],
            new FunctionScope('t.php', 'f'),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertSame(['SELECT 2'], array_map(static fn (QueryRecord $record): ?string => $record->pattern->text(), $recorder->records()));
    }

    public function testRecordStatementsMarksAStatementCombinedWhenEitherTheTextOrTheReadingIs(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();
        $plain = \SqlCatalog\Evaluation\Domain::literal('SELECT 1');
        $combined = \SqlCatalog\Evaluation\Domain::fromTerms([new \SqlCatalog\Evaluation\LiteralTerm('SELECT 2')], false, true);

        (new Interpreter(new ProgramIndex(), []))->recordStatements(
            $call,
            (new PdoExtension())->sinks()[0],
            [
                new Solution([$plain], []),
                new Solution([$plain], [], false, true),
                new Solution([$combined], []),
            ],
            new FunctionScope('t.php', 'f'),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertSame([false, true, true], array_map(static fn (QueryRecord $record): bool => $record->combined, $recorder->records()));
    }

    public function testRecordStatementsFilesTheStatementsOfEveryReadingUnderThePreparedHandle(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->prepare("SELECT 1"); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        (new Interpreter(new ProgramIndex(), []))->recordStatements(
            $call,
            (new PdoExtension())->sinks()[2],
            [
                new Solution([\SqlCatalog\Evaluation\Domain::literal('SELECT 1')], []),
                new Solution([\SqlCatalog\Evaluation\Domain::literal('SELECT 2')], []),
            ],
            new FunctionScope('t.php', 'f'),
            $recorder,
            new ValueBinder($recorder),
        );

        self::assertSame(
            ['SELECT 1', 'SELECT 2'],
            array_map(
                static fn (QueryRecord $record): ?string => $record->pattern->text(),
                $recorder->prepared('t.php:' . $call->getStartFilePos() . ':pdo.prepare'),
            ),
        );
    }

    public function testRecordUnmatchedKeysTheCallByItsFileAndOffset(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $x->query("SELECT 1");');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $recorder = new StatementRecorder();

        (new Interpreter(new ProgramIndex(), []))->recordUnmatched($call, new FunctionScope('t.php'), $recorder);

        self::assertSame('t.php:' . $call->getStartFilePos(), $recorder->records()[0]->siteKey);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;

#[CoversClass(Interpreter::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(ExpressionEvaluator::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Evaluation\PathSet::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
final class InterpreterTest extends TestCase
{
    public function testAnalyzeFindsStatementsInsideAFunctionBody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze($file);
        self::assertCount(1, $records);
        self::assertSame('f', $records[0]->site->function);
    }

    public function testAnalyzeFindsStatementsOutsideAnyFunction(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $d = new PDO("sqlite::memory:"); $d->query("SELECT 1");');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze($file);
        self::assertSame(FunctionScope::MAIN, $records[0]->site->function);
    }

    public function testAnalyzeStartsEachFileWithAFreshBudget(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        self::assertCount(1, $interpreter->analyze($file));
        self::assertCount(1, $interpreter->analyze($file));
    }

    public function testAnalyzeBodyLeavesParametersOpen(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d, string $t): void { $d->query("SELECT * FROM " . $t); }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze($file);
        self::assertSame(\SqlCatalog\Text\Origin::Parameter, $records[0]->pattern->holes()[0]->origin);
    }

    public function testAnalyzeBodySkipsADeclarationWithoutABody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php interface I { public function f(): void; }');
        $records = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->analyze($file);
        self::assertSame([], $records);
    }

    public function testAnalyzeBodyIsCalledWithTheBodyItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $recorder = new StatementRecorder();
        $interpreter = new Interpreter($index, (new PdoExtension())->sinks());
        $expressions = $interpreter->evaluatorFor($recorder);
        $body = (new NodeFinder())->findFirstInstanceOf($file->statements, FunctionLike::class);
        self::assertInstanceOf(FunctionLike::class, $body);

        $interpreter->analyzeBody($body, $file, $expressions);

        self::assertCount(1, $recorder->records());
    }

    public function testRestrictToNarrowsTheWalkToBodiesThatCanReachACall(): void
    {
        $interpreter = new Interpreter(new ProgramIndex(), []);
        $interpreter->restrictTo(['t.php:12' => true]);

        self::assertTrue($interpreter->reaches('t.php:12'));
        self::assertFalse($interpreter->reaches('t.php:main'));
    }

    public function testReachesAcceptsEveryBodyUntilTheWalkIsNarrowed(): void
    {
        self::assertTrue((new Interpreter(new ProgramIndex(), []))->reaches('t.php:main'));
    }

    public function testAnalyzeStillReportsACallInsideABodyItSkipped(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        $interpreter->restrictTo([]);

        $records = $interpreter->analyze($file);

        self::assertCount(1, $records);
        self::assertSame('unreached', $records[0]->site->sink);
    }

    public function testRecordUnmatchedKeepsADatabaseCallTheWalkNeverGotTo(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(PDO $d): void { $a = 1; $b = 2; $c = 3; $d->query("SELECT 1"); }',
        );
        $interpreter = new Interpreter(
            (new ProgramIndexBuilder())->build([$file]),
            (new PdoExtension())->sinks(),
            new EvaluationBudget(2),
        );

        $records = $interpreter->analyze($file);

        self::assertCount(1, $records);
        self::assertSame('unreached', $records[0]->site->sink);
        self::assertFalse($records[0]->pattern->isExact());
    }

    public function testRecordUnmatchedStaysQuietWhenTheWalkToldEveryCallApart(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(PDO $d): void { $d->query("SELECT 1"); }');
        $interpreter = new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks());
        $recorder = new StatementRecorder();
        $interpreter->analyze($file);

        $interpreter->recordUnmatched($file, $recorder);

        self::assertCount(1, $recorder->records());
    }

    public function testEvaluatorForWiresAnEvaluatorOntoTheGivenRecorder(): void
    {
        $recorder = new StatementRecorder();
        $evaluator = (new Interpreter(new ProgramIndex(), []))->evaluatorFor($recorder);
        self::assertSame([], $recorder->records());
        self::assertSame($evaluator->bodies(), $evaluator->bodies());
    }

    public function testEnclosingClassFindsTheClassABodyBelongsTo(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; class R { public function f(): void {} } function g(): void {}');
        $interpreter = new Interpreter(new ProgramIndex(), []);
        $bodies = (new NodeFinder())->findInstanceOf($file->statements, FunctionLike::class);
        self::assertSame('App\\R', $interpreter->enclosingClass($bodies[0]));
        self::assertNull($interpreter->enclosingClass($bodies[1]));
    }

    public function testNameOfNamesMethodsFunctionsAndClosures(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace App; class R { public function f(): void { $c = static function (): void {}; } } function g(): void {}',
        );
        $interpreter = new Interpreter(new ProgramIndex(), []);
        $bodies = (new NodeFinder())->findInstanceOf($file->statements, FunctionLike::class);
        self::assertSame('App\\R::f', $interpreter->nameOf($bodies[0], 'App\\R'));
        self::assertSame('App\\R::{closure}', $interpreter->nameOf($bodies[1], 'App\\R'));
        self::assertSame('App\\g', $interpreter->nameOf($bodies[2], null));
    }
}

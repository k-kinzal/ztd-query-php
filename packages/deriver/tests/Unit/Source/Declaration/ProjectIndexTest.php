<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Exception\InvalidInputException;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\CallSiteIndex;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectIndex::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallSiteIndex::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ProjectIndexTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testParsePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target() { $x="before"; $x="after"; return $x . ":done"; }');
        self::assertSame('after:done', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testClassesIncludesDeclarationsBeforeAnyGraphIsDemanded(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php class A {}');
        self::assertSame(['a'], array_keys($index->classes()));
        self::assertSame(0, $index->graphCount());
    }
    public function testSymbolsReturnsStableSourceIdentities(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function Z(){} function A(){}');
        self::assertSame(['A', 'Z', 'script:fixture.php'], $index->symbols());
    }
    public function testDiagnosticsKeepsSyntaxErrorsSeparateFromAnEmptyProgram(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function broken(');
        self::assertNotEmpty($index->diagnostics());
        self::assertSame('INCOMPLETE_SOURCE', $index->diagnostics()[0]->code);
        self::assertSame([], $index->symbols());
    }
    public function testGraphCountCountsOnlyDemandedBodiesAndReusesTheGraph(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){} function other(){}');
        self::assertSame(0, $index->graphCount());
        $first = $index->callable('target');
        self::assertNotNull($first);
        self::assertSame($first, $index->callable('TARGET'));
        self::assertSame(1, $index->graphCount());
    }
    public function testConstantCompilesARequestedInitializerOnce(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php const ANSWER = 40+2;');
        $constant = $index->constant('ANSWER');
        self::assertNotNull($constant);
        self::assertSame(['constant', 'constant', 'binary'], array_column($constant->blocks[0]->instructions, 'operation'));
        self::assertSame($constant, $index->constant('ANSWER'));
        self::assertNull($index->constant('answer'));
    }
    public function testCallOwnersDoesNotCompileUnrelatedBodies(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function used(){sink(1);} function other(){unrelated();}');
        self::assertSame(['used'], $index->callOwners('sink'));
        self::assertSame(0, $index->graphCount());
    }
    public function testRegisterReportsDuplicateCaseInsensitiveSymbols(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){}');
        $index->register($index->declarations['target']);
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
        self::assertCount(2, $index->symbols());
    }
    public function testBuilderRetainsTheCapturedBytes(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){}');
        self::assertSame('<?php function target(){}', $index->builder('fixture.php')->contents);
    }
    public function testCallableUsesCaseInsensitiveFunctionResolution(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function Target(){}');
        self::assertSame($index->callable('target'), $index->callable('\\TARGET'));
        self::assertNull($index->callable('missing'));
    }
    public function testRegisterClosureInheritsStrictModeWithoutCompilingIt(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php declare(strict_types=1);');
        $node = new \PhpParser\Node\Expr\ArrowFunction(['expr' => new \PhpParser\Node\Scalar\Int_(1)], ['startFilePos' => 31]);
        $symbol = $index->registerClosure($node, 'fixture.php', '');
        self::assertSame('closure:fixture.php:31', $symbol);
        self::assertSame(0, $index->graphCount());
        self::assertTrue($index->declarations[$symbol]->strict);
    }
    public function testParseRejectsCombinedSyntaxSizeInsteadOfReturningAPartialWorld(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php return 1;'),new SourceFile('b.php', '<?php return 2;')]);
        $this->expectException(InvalidInputException::class);
        new ProjectIndex('test', $input, new TargetProfile(), limits: new SourceLimits(nodes: 3));
    }
    public function testParseNormalizesPathsAndKeepsFilesHashesAndDeclarationsInStableOrder(): void
    {
        $first = '<?php function Z(){}';
        $second = '<?php function A(){}';
        $input = new ProjectInput([new SourceFile('z/./File.php', $first, true),new SourceFile('a\\File.php', $second)]);
        $index = new ProjectIndex('snapshot', $input, new TargetProfile());
        self::assertSame(['a/File.php','z/File.php'], array_keys($index->files));
        self::assertSame(['a/File.php' => hash('sha256', $second),'z/File.php' => hash('sha256', $first)], $index->fileHashes);
        self::assertSame('z/File.php', $index->files['z/File.php']->path);
        self::assertTrue($index->files['z/File.php']->declarationsOnly);
        self::assertSame(['A','Z','script:a/File.php'], $index->symbols());
        self::assertSame(['a','script:a/File.php','z'], array_keys($index->declarations));
        self::assertSame(0, $index->graphCount());
    }

    public function testParsePreservesScriptRangesStrictnessAndCaseSensitiveFileIdentities(): void
    {
        $upper = '<?php declare(strict_types=1);return 1;';
        $lower = '<?php return 2;';
        $index = new ProjectIndex('snapshot', new ProjectInput([new SourceFile('A.php', $upper),new SourceFile('a.php', $lower)]), new TargetProfile());
        $first = $index->callable('script:A.php');
        $second = $index->callable('script:a.php');
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first, $second);
        self::assertTrue($first->strict);
        self::assertFalse($second->strict);
        self::assertSame('snapshot', $first->source->snapshotId);
        self::assertSame('A.php', $first->source->path);
        self::assertSame(0, $first->source->start);
        self::assertSame(strlen($upper), $first->source->end);
        self::assertSame(strlen($lower), $second->source->end);
        self::assertSame([], $index->diagnostics());
    }

    public function testParseAdmitsTheExactCombinedSyntaxNodeLimit(): void
    {
        $index = new ProjectIndex('snapshot', new ProjectInput([new SourceFile('a.php', '<?php return 1;'),new SourceFile('b.php', '<?php return 2;')]), new TargetProfile(), limits:new SourceLimits(nodes:4));
        self::assertSame(4, $index->syntaxNodes);
        self::assertSame(['script:a.php','script:b.php'], $index->symbols());
    }

    public function testParseReportsEveryInvalidFileWithItsOwnSourceCoordinates(): void
    {
        $index = new ProjectIndex('snapshot', new ProjectInput([new SourceFile('a.php', "<?php\nfunction broken("),new SourceFile('b.php', '<?php function other(')]), new TargetProfile());
        $issues = $index->diagnostics();
        self::assertCount(2, $issues);
        self::assertSame(['INCOMPLETE_SOURCE','INCOMPLETE_SOURCE'], array_column($issues, 'code'));
        self::assertSame('a.php', $issues[0]->at->path);
        self::assertSame('snapshot', $issues[0]->at->snapshotId);
        self::assertSame(2, $issues[0]->at->line);
        self::assertSame(0, $issues[0]->at->start);
        self::assertSame(strlen("<?php\nfunction broken("), $issues[0]->at->end);
        self::assertSame('valid-php-source', $issues[0]->missingCapability);
        self::assertSame('b.php', $issues[1]->at->path);
        self::assertSame([], $index->symbols());
    }

    public function testRegisterRetainsTheOriginalDeclarationAndReportsTheDuplicateLocation(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function TARGET(){return 1;}function target(){return 2;}');
        self::assertCount(1, $index->diagnostics());
        self::assertSame('duplicate:target', $index->diagnostics()[0]->operation);
        self::assertGreaterThan($index->declarations['target']->node->getStartFilePos(), $index->diagnostics()[0]->at->start);
        $body = $index->callable('target');
        self::assertNotNull($body);
        self::assertSame('TARGET', $body->symbol);
        self::assertSame(1, $body->blocks[0]->instructions[0]->constant?->literal);
    }

    public function testRegisterClosureKeepsLexicalScopesAndRecordsEveryCapturedSource(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php declare(strict_types=1);');
        $node = new \PhpParser\Node\Expr\ArrowFunction(['expr' => new \PhpParser\Node\Scalar\Int_(1)], ['startFilePos' => 31]);
        $index->capturedClosures = [];
        $a = $index->registerClosure($node, 'fixture.php', 'Alpha');
        $b = $index->registerClosure($node, 'fixture.php', 'Beta');
        $again = $index->registerClosure($node, 'fixture.php', 'Alpha');
        self::assertSame('closure:fixture.php:31:scope:Alpha', $a);
        self::assertSame('closure:fixture.php:31:scope:Beta', $b);
        self::assertSame($a, $again);
        self::assertSame([$a,$b], array_keys($index->capturedClosures));
        self::assertSame($node, $index->declarations[$a]->node);
        self::assertTrue($index->declarations[$a]->strict);
        self::assertSame('Alpha', $index->declarations[$a]->className);
        self::assertSame('Beta', $index->declarations[$b]->className);
        self::assertSame([], $index->diagnostics());
        self::assertSame(0, $index->graphCount());
    }

    public function testConstantPreservesConstantCaseAndNormalizesOnlyItsDeclaringClass(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php class Box{const VALUE=1;const value=2;}');
        $upper = $index->constant('Box::VALUE');
        $lower = $index->constant('Box::value');
        self::assertNotNull($upper);
        self::assertNotNull($lower);
        self::assertNotSame($upper, $lower);
        self::assertSame($upper, $index->constant('bOX::VALUE'));
        self::assertSame(1, $upper->blocks[0]->instructions[0]->constant?->literal);
        self::assertSame(2, $lower->blocks[0]->instructions[0]->constant?->literal);
        self::assertSame('Box', $upper->className);
        self::assertNull($index->constant('Box::missing'));
        self::assertSame(2, $index->graphCount());
    }

    public function testBuilderReusesTheCapturedLineMapAndSnapshotOwnership(): void
    {
        $index = \Tests\Fake\SourceFixture::index("<?php\nfunction target(){}");
        $builder = $index->builder('fixture.php');
        self::assertSame($index->lineMaps['fixture.php'], $builder->lines);
        self::assertSame('test', $builder->snapshot);
        self::assertSame('fixture.php', $builder->path);
        self::assertSame("<?php\nfunction target(){}", $builder->contents);
    }
}

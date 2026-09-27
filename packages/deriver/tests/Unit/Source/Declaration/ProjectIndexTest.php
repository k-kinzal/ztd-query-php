<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Declaration\ProjectIndex;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallSiteIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CallableCompilerTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCompilePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target() {$x="first";$a=fn()=>$x;$b=function()use(&$x){return $x;};$x="second";return [$a(),$b()];}');
        self::assertSame(['first', 'second'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testParametersCapturesReferenceAndDefaultExpressionSeparately(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target(int &$n = 2, ...$rest) {}');
        $source = $index->declarations['target'];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $source->node);
        $parameters = (new \Deriver\Internal\Frontend\Php\CallableCompiler($index))->parameters($source->node, $source);
        self::assertTrue($parameters[0]->byReference);
        self::assertNotNull($parameters[0]->default);
        self::assertSame(2, $parameters[0]->default->blocks[0]->instructions[0]->constant?->native());
        self::assertTrue($parameters[1]->variadic);
    }
    public function testExpressionProducesAnIndependentReturnGraph(): void
    {
        $compiler = new \Deriver\Internal\Frontend\Php\CallableCompiler(\Tests\Fake\FrontendFixture::index());
        $body = $compiler->expression(new \PhpParser\Node\Scalar\Int_(7), 'fixture.php', 'default:test');
        self::assertSame('default:test', $body->symbol);
        self::assertSame(7, $body->blocks[0]->instructions[0]->constant?->native());
        self::assertSame($body->blocks[0]->instructions[0]->result, $body->blocks[0]->terminator->operand);
    }
    public function testTypePreservesNullableUnionsAndIntersections(): void
    {
        $compiler = new \Deriver\Internal\Frontend\Php\CallableCompiler(\Tests\Fake\FrontendFixture::index());
        self::assertSame('int|null', $compiler->type(new \PhpParser\Node\NullableType(new \PhpParser\Node\Identifier('int'))));
        self::assertSame('A&B', $compiler->type(new \PhpParser\Node\IntersectionType([new \PhpParser\Node\Name('A'), new \PhpParser\Node\Name('B')])));
        self::assertSame('mixed', $compiler->type(null));
    }
    public function testCapturesExcludesArrowParameters(): void
    {
        $arrow = new \PhpParser\Node\Expr\ArrowFunction(['params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('x'))], 'expr' => new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Expr\Variable('outside'))]);
        self::assertSame(['outside' => false], (new \Deriver\Internal\Frontend\Php\CallableCompiler(\Tests\Fake\FrontendFixture::index()))->captures($arrow));
    }
    public function testFreeVariablesDeduplicatesLexicalReads(): void
    {
        $node = new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Expr\Variable('x'));
        self::assertSame(['x' => false], (new \Deriver\Internal\Frontend\Php\CallableCompiler(\Tests\Fake\FrontendFixture::index()))->freeVariables($node));
    }
    public function testCompileOmitsAnExternalImplementationButRetainsItsSignature(): void
    {
        $index = new \Deriver\Internal\Frontend\Php\ProjectIndex('test', new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('stub.php', '<?php function sample(int &$value): int {return 99;}', true)]), new \Deriver\Api\Project\TargetProfile());
        $body = (new \Deriver\Internal\Frontend\Php\CallableCompiler($index))->compile($index->declarations['sample']);
        self::assertTrue($body->external);
        self::assertTrue($body->parameters[0]->byReference);
        self::assertSame(['external-body'], array_column($body->blocks[0]->instructions, 'operation'));
    }
    /**
     * @param string $source Trusted default initializer fixture
     * @param string $expectedJson Independently observed PHP 8.3 result
     * @throws JsonException If expected fixture data cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInitializers')]
    public function testParametersPreservesTheDeclaringScalarModeForNewDefaults(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerInitializers(): array
    {
        return \Tests\Fake\Programs\InitializerPrograms::cases();
    }
    /**
     * @param string $library Captured declaration file
     * @param string $application Captured caller file
     * @param string $expectedJson PHP 8.3 observation
     * @throws JsonException If captured fixture data cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInitializerFiles')]
    public function testParametersUsesTheDeclarationFileModeAcrossCallerFiles(string $library, string $application, string $expectedJson): void
    {
        $session = (new \Deriver\Analyzer())->open(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('library.php', $library),new \Deriver\Api\Project\SourceFile('app.php', $application)]));
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerInitializerFiles(): array
    {
        return \Tests\Fake\Programs\InitializerPrograms::fileCases();
    }
}

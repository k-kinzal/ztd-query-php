<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use Deriver\Analyzer;
use Deriver\ControlFlow\Parameter;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Declaration\ProjectIndex;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableCompiler::class)]
#[UsesClass(\Deriver\Analysis\CallObservations::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(\Deriver\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Reference\Observation::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallSiteIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Members::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
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
        $index = \Tests\Fake\SourceFixture::index('<?php function target(int &$n = 2, ...$rest) {}');
        $source = $index->declarations['target'];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $source->node);
        $parameters = (new CallableCompiler($index))->parameters($source->node, $source);
        self::assertTrue($parameters[0]->byReference);
        self::assertNotNull($parameters[0]->default);
        self::assertSame(2, $parameters[0]->default->blocks[0]->instructions[0]->constant?->native());
        self::assertTrue($parameters[1]->variadic);
    }
    public function testExpressionProducesAnIndependentReturnGraph(): void
    {
        $compiler = new CallableCompiler(\Tests\Fake\SourceFixture::index());
        $body = $compiler->expression(new \PhpParser\Node\Scalar\Int_(7), 'fixture.php', 'default:test');
        self::assertSame('default:test', $body->symbol);
        self::assertSame(7, $body->blocks[0]->instructions[0]->constant?->native());
        self::assertSame($body->blocks[0]->instructions[0]->result, $body->blocks[0]->terminator->operand);
    }
    public function testTypePreservesNullableUnionsAndIntersections(): void
    {
        $compiler = new CallableCompiler(\Tests\Fake\SourceFixture::index());
        self::assertSame('int|null', $compiler->type(new \PhpParser\Node\NullableType(new \PhpParser\Node\Identifier('int'))));
        self::assertSame('A&B', $compiler->type(new \PhpParser\Node\IntersectionType([new \PhpParser\Node\Name('A'), new \PhpParser\Node\Name('B')])));
        self::assertSame('mixed', $compiler->type(null));
    }
    public function testCapturesExcludesArrowParameters(): void
    {
        $arrow = new \PhpParser\Node\Expr\ArrowFunction(['params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('x'))], 'expr' => new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Expr\Variable('outside'))]);
        self::assertSame(['outside' => false], (new CallableCompiler(\Tests\Fake\SourceFixture::index()))->captures($arrow));
    }
    public function testFreeVariablesDeduplicatesLexicalReads(): void
    {
        $node = new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Expr\Variable('x'));
        self::assertSame(['x' => false], (new CallableCompiler(\Tests\Fake\SourceFixture::index()))->freeVariables($node));
    }
    public function testCompileOmitsAnExternalImplementationButRetainsItsSignature(): void
    {
        $index = new ProjectIndex('test', new ProjectInput([new SourceFile('stub.php', '<?php function sample(int &$value): int {return 99;}', true)]), new TargetProfile());
        $body = (new CallableCompiler($index))->compile($index->declarations['sample']);
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
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('library.php', $library),new SourceFile('app.php', $application)]));
        $result = $session->derive(new ReturnQuery('target'));
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
    /**
     * @param string $source Trusted promoted-property fixture
     * @param string $expectedJson PHP 8.3 observation
     * @throws JsonException If fixture data cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPromotedReferences')]
    public function testPromotionsPreserveAliasesAndReadonlyErrors(string $source, string $expectedJson): void
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
    public static function providerPromotedReferences(): array
    {
        return \Tests\Fake\Programs\PromotedReferencePrograms::cases();
    }
    /**
     * @param string $reference Parameter passing syntax
     * @param string $expectedKind Expected selected value category
     * @param int|string $expectedLiteral Preserved symbolic identity or updated value
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSymbolicPromotions')]
    public function testPromotionsInitializeSymbolicConstructorObservations(string $reference, string $expectedKind, int|string $expectedLiteral): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function sink($v){}class Box{function __construct(public int ' . $reference . '$value){$value=2;sink($this->value);}}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        $values = array_column(array_column($result->normalOutcomes, 'values'), 'value');
        self::assertSame([$expectedKind], array_values(array_unique(array_column($values, 'kind'))));
        self::assertSame([$expectedLiteral], array_values(array_unique(array_column($values, 'literal'))));
    }

    /**
     * @return array<string,array{string,string,int|string}>
     */
    public static function providerSymbolicPromotions(): array
    {
        return ['by value' => ['', 'parameter', 'value'], 'by reference' => ['&', 'constant', 2]];
    }
}

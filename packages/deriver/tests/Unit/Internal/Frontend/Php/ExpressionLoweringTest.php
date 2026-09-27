<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLowerPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return [2=>"left","x"=>1]+[2=>"right",3=>"new"];}');
        self::assertSame([2 => 'left', 'x' => 1, 3 => 'new'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testOperationReadsAnAddressableVariable(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\ExpressionLowering($l))->operation(new \PhpParser\Node\Expr\Variable('x'));
        self::assertSame(['local', 'read'], array_column($l->graph->instructions[0], 'operation'));
    }
    public function testBinaryMarksInteractingEffectsWithUnspecifiedOrder(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\PostInc(new \PhpParser\Node\Expr\Variable('x')), new \PhpParser\Node\Expr\Variable('x'));
        (new \Deriver\Internal\Frontend\Php\ExpressionLowering($l))->binary($node);
        self::assertSame(['local', 'increment', 'local', 'read', 'binary', 'uncertain-order'], array_column($l->graph->instructions[0], 'operation'));
    }
    public function testOtherMakesEvalASymbolTableBoundary(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\ExpressionLowering($l))->other(new \PhpParser\Node\Expr\Eval_(new \PhpParser\Node\Scalar\String_('return 1;')));
        self::assertSame('symbol-table-boundary', $l->graph->instructions[0][1]->operation);
    }
    public function testComputedRegistersAClosureWithoutLoweringItsBody(): void
    {
        $l = \Tests\Fake\FrontendFixture::lowering();
        (new \Deriver\Internal\Frontend\Php\ExpressionLowering($l))->computed(new \PhpParser\Node\Expr\ArrowFunction(['expr' => new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('sideEffect'))]));
        self::assertSame(['closure'], array_column($l->graph->instructions[0], 'operation'));
        self::assertSame(0, $l->index->graphCount());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testMagicUsesLexicalNamesForSeparateDefaultGraphs(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{static function value($name=__METHOD__){return $name;}}function target(){return Box::value();}');
        self::assertSame('Box::value', $result->normalOutcomes[0]->values['return']->native());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLiteralNodes')]
    public function testLowerPreservesLiteralTypesAndBytes(\PhpParser\Node\Expr $node, mixed $expected): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->lower($node);
        self::assertCount(1, $lowering->graph->instructions[0]);
        self::assertSame('constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame($expected, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    /**
     * @return iterable<string,array{\PhpParser\Node\Expr,mixed}>
     */
    public static function providerLiteralNodes(): iterable
    {
        yield 'integer' => [new \PhpParser\Node\Scalar\Int_(42),42];
        yield 'float' => [new \PhpParser\Node\Scalar\Float_(2.5),2.5];
        yield 'string' => [new \PhpParser\Node\Scalar\String_("\x00\xff"),"\x00\xff"];
    }
    public function testLowerRetainsUnresolvedConstantNames(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->lower(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('EXAMPLE')));
        self::assertSame('constant-fetch', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('EXAMPLE', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLexicalMagic')]
    public function testMagicSelectsOnlyTheMatchingCapturedLexicalName(\PhpParser\Node\Scalar\MagicConst $node, string $key, string $expected): void
    {
        $node->setAttribute('deriver-lexical', [$key => $expected,'unrelated' => 'wrong']);
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->magic($node);
        self::assertSame('constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame($expected, $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    /**
     * @return iterable<string,array{\PhpParser\Node\Scalar\MagicConst,string,string}>
     */
    public static function providerLexicalMagic(): iterable
    {
        yield 'function' => [new \PhpParser\Node\Scalar\MagicConst\Function_(),'function','Example\\run'];
        yield 'method' => [new \PhpParser\Node\Scalar\MagicConst\Method(),'method','Example\\Box::run'];
        yield 'namespace' => [new \PhpParser\Node\Scalar\MagicConst\Namespace_(),'namespace','Example'];
    }
    public function testLowerPreservesRuntimeMagicWhenLexicalMetadataIsUnavailable(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Scalar\MagicConst\Line();
        $node->setAttribute('deriver-lexical', ['function' => 'irrelevant']);
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->lower($node);
        self::assertSame('magic-constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('__LINE__', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    public function testMagicRejectsNonStringLexicalMetadata(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $node = new \PhpParser\Node\Scalar\MagicConst\Function_();
        $node->setAttribute('deriver-lexical', ['function' => 42]);
        (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->magic($node);
        self::assertSame('magic-constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('__FUNCTION__', $lowering->graph->instructions[0][0]->name);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnaryAndCasts')]
    public function testOtherPreservesUnaryAndCastOperations(\PhpParser\Node\Expr $node, string $operation, string $name): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other($node);
        self::assertSame(['constant',$operation], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame($name, $lowering->graph->instructions[0][1]->name);
        self::assertSame([$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[0][1]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][1]->result);
    }
    /**
     * @return iterable<string,array{\PhpParser\Node\Expr,string,string}>
     */
    public static function providerUnaryAndCasts(): iterable
    {
        $value = new \PhpParser\Node\Scalar\Int_(5);
        yield 'boolean not' => [new \PhpParser\Node\Expr\BooleanNot($value),'unary','Expr_BooleanNot'];
        yield 'unary minus' => [new \PhpParser\Node\Expr\UnaryMinus($value),'unary','Expr_UnaryMinus'];
        yield 'unary plus' => [new \PhpParser\Node\Expr\UnaryPlus($value),'unary','Expr_UnaryPlus'];
        yield 'bitwise not' => [new \PhpParser\Node\Expr\BitwiseNot($value),'unary','Expr_BitwiseNot'];
        yield 'integer cast' => [new \PhpParser\Node\Expr\Cast\Int_($value),'cast','Int'];
        yield 'string cast' => [new \PhpParser\Node\Expr\Cast\String_($value),'cast','String'];
        yield 'boolean cast' => [new \PhpParser\Node\Expr\Cast\Bool_($value),'cast','Bool'];
        yield 'float cast' => [new \PhpParser\Node\Expr\Cast\Double($value),'cast','Double'];
        yield 'array cast' => [new \PhpParser\Node\Expr\Cast\Array_($value),'cast','Array'];
        yield 'object cast' => [new \PhpParser\Node\Expr\Cast\Object_($value),'cast','Object'];
    }
    public function testOtherPreservesCloneIdentityAsAnExplicitOperation(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Clone_(new \PhpParser\Node\Expr\Variable('object')));
        self::assertSame(['local','read','clone'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheThrowOperandAndItsResultRegister(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Throw_(new \PhpParser\Node\Expr\Variable('error')));
        self::assertSame(['local','read','throw'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsBothClassConstantNames(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\ClassConstFetch(new \PhpParser\Node\Name('Box'), 'VALUE'));
        self::assertSame(['constant','constant','class-constant'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('VALUE', $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][0]->result,$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheInstanceofReceiverAndClass(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Instanceof_(new \PhpParser\Node\Expr\Variable('object'), new \PhpParser\Node\Name('Box')));
        self::assertSame(['local','read','constant','instanceof'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][2]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][1]->result,$lowering->graph->instructions[0][2]->result], $lowering->graph->instructions[0][3]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][3]->result);
    }
    public function testOtherCapturesIncludeBoundariesWithoutReadingTheFile(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Include_(new \PhpParser\Node\Scalar\String_('unread.php'), \PhpParser\Node\Expr\Include_::TYPE_REQUIRE_ONCE));
        self::assertSame(['constant','symbol-table-boundary'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('unread.php', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('INCLUDE_SEMANTICS_UNSUPPORTED', $lowering->graph->instructions[0][1]->name);
        self::assertSame([$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[0][1]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][1]->result);
    }
    public function testComputedRetainsAnUnsupportedExpressionTag(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->computed(new \PhpParser\Node\Expr\Print_(new \PhpParser\Node\Scalar\String_('not emitted')));
        self::assertSame(['unsupported'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Expr_Print', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    public function testComputedReadsOffsetsWithoutCreatingAnAddress(): void
    {
        $lowering = \Tests\Fake\FrontendFixture::lowering();
        $register = (new \Deriver\Internal\Frontend\Php\ExpressionLowering($lowering))->computed(new \PhpParser\Node\Expr\ArrayDimFetch(new \PhpParser\Node\Expr\Array_([]), new \PhpParser\Node\Scalar\Int_(2)));
        self::assertSame(['constant','constant','array-read'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][0]->result,$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame(2, $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerClassFetchSyntax')]
    public function testLowerKeepsLiteralClassSyntaxDistinctFromEvaluatedClassValues(string $expression, bool $literal, bool $className): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target($x,$name){return '.$expression.';}');
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (\Deriver\Internal\IR\Instruction $instruction): bool => $instruction->operation === 'class-constant'));
        self::assertCount(1, $instructions);
        self::assertSame($literal, $instructions[0]->attributes['literal-class']);
        self::assertSame($className, $instructions[0]->attributes['class-name']);
    }

    /**
     * @return iterable<string,array{string,bool,bool}>
     */
    public static function providerClassFetchSyntax(): iterable
    {
        yield 'literal class name' => ['Missing::class',true,true];
        yield 'dynamic class name' => ['$x::class',false,true];
        yield 'literal class dynamic constant' => ['Box::{$name}',true,false];
        yield 'dynamic class dynamic constant' => ['$x::{$name}',false,false];
        yield 'class keyword case' => ['Box::ClAsS',true,true];
        yield 'ordinary constant' => ['Box::VALUE',true,false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerClassOperandSyntax')]
    public function testOtherPreservesWhetherAClassOperandIsLiteral(string $expression, bool $literal): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target($class,$value){return ' . $expression . ';}');
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (\Deriver\Internal\IR\Instruction $instruction): bool => $instruction->operation === 'instanceof'));
        self::assertCount(1, $instructions);
        self::assertSame($literal, $instructions[0]->attributes['literal-class']);
    }

    /**
     * @return iterable<string,array{string,bool}>
     */
    public static function providerClassOperandSyntax(): iterable
    {
        yield '$value instanceof Box' => ['$value instanceof Box', true];
        yield '$value instanceof $class' => ['$value instanceof $class', false];
    }
}

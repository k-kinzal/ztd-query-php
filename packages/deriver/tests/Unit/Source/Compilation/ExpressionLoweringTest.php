<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use Deriver\ControlFlow\Instruction;
use Deriver\Source\Compilation\ExpressionLowering;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressionLowering::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
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
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ExpressionLowering($l))->operation(new \PhpParser\Node\Expr\Variable('x'));
        self::assertSame(['local', 'read'], array_column($l->graph->instructions[0], 'operation'));
    }
    public function testBinaryMarksInteractingEffectsWithUnspecifiedOrder(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\PostInc(new \PhpParser\Node\Expr\Variable('x')), new \PhpParser\Node\Expr\Variable('x'));
        (new ExpressionLowering($l))->binary($node);
        self::assertSame(['local', 'increment', 'local', 'read', 'binary', 'uncertain-order'], array_column($l->graph->instructions[0], 'operation'));
    }
    public function testOtherMakesEvalASymbolTableBoundary(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ExpressionLowering($l))->other(new \PhpParser\Node\Expr\Eval_(new \PhpParser\Node\Scalar\String_('return 1;')));
        self::assertSame('symbol-table-boundary', $l->graph->instructions[0][1]->operation);
    }
    public function testComputedRegistersAClosureWithoutLoweringItsBody(): void
    {
        $l = \Tests\Fake\SourceFixture::lowering();
        (new ExpressionLowering($l))->computed(new \PhpParser\Node\Expr\ArrowFunction(['expr' => new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('sideEffect'))]));
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
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->lower($node);
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
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->lower(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('EXAMPLE')));
        self::assertSame('constant-fetch', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('EXAMPLE', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLexicalMagic')]
    public function testMagicSelectsOnlyTheMatchingCapturedLexicalName(\PhpParser\Node\Scalar\MagicConst $node, string $key, string $expected): void
    {
        $node->setAttribute('deriver-lexical', [$key => $expected,'unrelated' => 'wrong']);
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->magic($node);
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
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Scalar\MagicConst\Line();
        $node->setAttribute('deriver-lexical', ['function' => 'irrelevant']);
        $register = (new ExpressionLowering($lowering))->lower($node);
        self::assertSame('magic-constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('__LINE__', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    public function testMagicRejectsNonStringLexicalMetadata(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $node = new \PhpParser\Node\Scalar\MagicConst\Function_();
        $node->setAttribute('deriver-lexical', ['function' => 42]);
        (new ExpressionLowering($lowering))->magic($node);
        self::assertSame('magic-constant', $lowering->graph->instructions[0][0]->operation);
        self::assertSame('__FUNCTION__', $lowering->graph->instructions[0][0]->name);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnaryAndCasts')]
    public function testOtherPreservesUnaryAndCastOperations(\PhpParser\Node\Expr $node, string $operation, string $name): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other($node);
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
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Clone_(new \PhpParser\Node\Expr\Variable('object')));
        self::assertSame(['local','read','clone'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheThrowOperandAndItsResultRegister(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Throw_(new \PhpParser\Node\Expr\Variable('error')));
        self::assertSame(['local','read','throw'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsBothClassConstantNames(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\ClassConstFetch(new \PhpParser\Node\Name('Box'), 'VALUE'));
        self::assertSame(['constant','constant','class-constant'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('VALUE', $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][0]->result,$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }
    public function testOtherKeepsTheInstanceofReceiverAndClass(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Instanceof_(new \PhpParser\Node\Expr\Variable('object'), new \PhpParser\Node\Name('Box')));
        self::assertSame(['local','read','constant','instanceof'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Box', $lowering->graph->instructions[0][2]->constant?->native());
        self::assertSame([$lowering->graph->instructions[0][1]->result,$lowering->graph->instructions[0][2]->result], $lowering->graph->instructions[0][3]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][3]->result);
    }
    public function testOtherCapturesIncludeBoundariesWithoutReadingTheFile(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->other(new \PhpParser\Node\Expr\Include_(new \PhpParser\Node\Scalar\String_('unread.php'), \PhpParser\Node\Expr\Include_::TYPE_REQUIRE_ONCE));
        self::assertSame(['constant','symbol-table-boundary'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('unread.php', $lowering->graph->instructions[0][0]->constant?->native());
        self::assertSame('INCLUDE_SEMANTICS_UNSUPPORTED', $lowering->graph->instructions[0][1]->name);
        self::assertSame([$lowering->graph->instructions[0][0]->result], $lowering->graph->instructions[0][1]->operands);
        self::assertSame($register, $lowering->graph->instructions[0][1]->result);
    }
    public function testComputedRetainsAnUnsupportedExpressionTag(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->computed(new \PhpParser\Node\Expr\Print_(new \PhpParser\Node\Scalar\String_('not emitted')));
        self::assertSame(['unsupported'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame('Expr_Print', $lowering->graph->instructions[0][0]->name);
        self::assertSame($register, $lowering->graph->instructions[0][0]->result);
    }
    public function testComputedReadsOffsetsWithoutCreatingAnAddress(): void
    {
        $lowering = \Tests\Fake\SourceFixture::lowering();
        $register = (new ExpressionLowering($lowering))->computed(new \PhpParser\Node\Expr\ArrayDimFetch(new \PhpParser\Node\Expr\Array_([]), new \PhpParser\Node\Scalar\Int_(2)));
        self::assertSame(['constant','constant','array-read'], array_column($lowering->graph->instructions[0], 'operation'));
        self::assertSame([$lowering->graph->instructions[0][0]->result,$lowering->graph->instructions[0][1]->result], $lowering->graph->instructions[0][2]->operands);
        self::assertSame(2, $lowering->graph->instructions[0][1]->constant?->native());
        self::assertSame($register, $lowering->graph->instructions[0][2]->result);
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerClassFetchSyntax')]
    public function testLowerKeepsLiteralClassSyntaxDistinctFromEvaluatedClassValues(string $expression, bool $literal, bool $className): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target($x,$name){return '.$expression.';}');
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (Instruction $instruction): bool => $instruction->operation === 'class-constant'));
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
        $index = \Tests\Fake\SourceFixture::index('<?php function target($class,$value){return ' . $expression . ';}');
        $body = $index->callable('target');
        self::assertNotNull($body);
        $instructions = array_values(array_filter($body->blocks[0]->instructions, static fn (Instruction $instruction): bool => $instruction->operation === 'instanceof'));
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

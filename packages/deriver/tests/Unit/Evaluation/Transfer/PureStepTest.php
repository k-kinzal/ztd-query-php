<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Project\Configuration;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(PureStep::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
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
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
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
#[UsesClass(Configuration::class)]
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
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class PureStepTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEvaluatePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=0;$a="known";$b=$a??++$x;return [$b,$x];}');
        self::assertSame(['known', 0], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testNotNullDistinguishesAnUninitializedCellFromAFalseValue(): void
    {
        $transfer = new PureStep(SolverFixture::context());
        self::assertSame(false, $transfer->notNull(new Term('uninitialized'))->native());
        self::assertSame(true, $transfer->notNull(Term::constant(false))->native());
    }
    public function testConstantUsesTheTargetVersion(): void
    {
        $transfer = new PureStep(SolverFixture::context());
        $instruction = new Instruction('version', 'constant-fetch', new SourceRef('test', 'fixture.php', 0, 1), 'result');
        self::assertSame(80300, $transfer->constant('PHP_VERSION_ID', $instruction)->native());
    }
    public function testMagicUsesTheCapturedSourceLine(): void
    {
        $transfer = new PureStep(SolverFixture::context());
        $body = SolverFixture::context()->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('line', 'magic-constant', new SourceRef('test', 'fixture.php', 0, 1, 37), 'result', name:'__LINE__');
        self::assertSame(37, $transfer->magic($body, $instruction)->native());
    }
    public function testArrayReadDereferencesSharedCells(): void
    {
        $transfer = new PureStep(SolverFixture::context());
        $state = new State();
        $cell = $state->memory->allocate(Term::constant(7));
        $value = $transfer->arrayRead(Term::array([new Term('cell', $cell->root)]), Term::constant(0), $state);
        self::assertSame(7, $value->native());
    }
    public function testExternalCreatesDistinctEvaluationEvents(): void
    {
        $transfer = new PureStep(SolverFixture::context());
        $state = new State();
        $instruction = new Instruction('external', 'external', new SourceRef('test', 'fixture.php', 0, 1), 'result', name:'clock');
        self::assertNotSame($transfer->external($instruction, $state)->literal, $transfer->external($instruction, $state)->literal);
    }
    public function testBinaryRecordsTargetWarningsWithoutHostRangeConversions(): void
    {
        $context = SolverFixture::context();
        $at = new SourceRef('test', 'fixture.php', 0, 1);
        $instruction = new Instruction('op', 'binary', $at, 'result', name:'%');
        $value = (new PureStep($context))->binary($instruction, Term::constant(3), Term::constant(0.1));
        self::assertSame('DivisionByZeroError', $value->literal);
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testNotNullRetainsTheConfidentialInputLabel(): void
    {
        $step = new PureStep(SolverFixture::context());
        self::assertTrue($step->notNull(Term::constant(null, true))->isSecret());
        self::assertTrue($step->notNull(new Term('object', 'a', secret: true))->isSecret());
    }

    /**
     * @param scalar|null $expected Known target constant
     */
    #[DataProvider('providerConstants')]
    public function testConstantReadsTargetConstantsIndependentlyOfTheHost(string $name, int|float|string|bool|null $expected): void
    {
        $step = new PureStep(SolverFixture::context());
        $instruction = new Instruction('constant', 'constant-fetch', new SourceRef('test', 'fixture.php', 0, 1));
        self::assertSame($expected, $step->constant($name, $instruction)->native());
    }

    /**
     * @return array<string,array{string,scalar|null}>
     */
    public static function providerConstants(): array
    {
        return [
            'null' => ['NULL',null], 'true' => ['TRUE',true], 'false' => ['FALSE',false],
            'integer size' => ['PHP_INT_SIZE',8], 'integer max' => ['PHP_INT_MAX',9223372036854775807], 'integer min' => ['PHP_INT_MIN',-9223372036854775807 - 1],
            'normal sort' => ['SORT_REGULAR',0], 'numeric sort' => ['SORT_NUMERIC',1], 'string sort' => ['SORT_STRING',2], 'case sort' => ['SORT_FLAG_CASE',8],
            'normal count' => ['COUNT_NORMAL',0], 'recursive count' => ['COUNT_RECURSIVE',1], 'both filter' => ['ARRAY_FILTER_USE_BOTH',1], 'key filter' => ['ARRAY_FILTER_USE_KEY',2],
        ];
    }

    public function testConstantUsesConfiguredIdentityAndRecordsMissingNames(): void
    {
        $configured = Term::constant('supplied', true);
        $context = SolverFixture::context(configuration:new Configuration(environment:['constant:EXAMPLE' => $configured]));
        $step = new PureStep($context);
        $instruction = new Instruction('constant', 'constant-fetch', new SourceRef('test', 'fixture.php', 0, 1));
        self::assertSame($configured, $step->constant('EXAMPLE', $instruction));
        $missing = $step->constant('MISSING', $instruction);
        self::assertSame('INCOMPLETE_SOURCE', $missing->literal);
        self::assertSame(['constant:MISSING'], array_column(array_values($context->frontiers), 'operation'));
    }

    #[DataProvider('providerMagic')]
    public function testMagicPreservesCapturedPathsAndCallableIdentity(string $name, int|string $expected): void
    {
        $source = new SourceRef('test', '/project/src/file.php', 10, 20, 37);
        $callable = new CallableGraph('run', [], [], $source, className:'Box');
        $instruction = new Instruction('magic', 'magic-constant', $source, 'result', name:$name);
        self::assertSame($expected, (new PureStep(SolverFixture::context()))->magic($callable, $instruction)->native());
    }

    /**
     * @return array<string,array{string,int|string}>
     */
    public static function providerMagic(): array
    {
        return ['line' => ['__LINE__',37],'file' => ['__FILE__','/project/src/file.php'],'dir' => ['__DIR__','/project/src'],'class' => ['__CLASS__','Box'],'method' => ['__METHOD__','run'],'function' => ['__FUNCTION__','run'],'absent' => ['other','']];
    }

    #[DataProvider('providerPresence')]
    public function testNotNullDistinguishesPresenceFromTruthiness(Term $value, bool $expected): void
    {
        $result = (new PureStep(SolverFixture::context()))->notNull($value);
        self::assertSame($expected, $result->native());
        self::assertSame($value->isSecret(), $result->isSecret());
    }

    /**
     * @return array<string,array{Term,bool}>
     */
    public static function providerPresence(): array
    {
        return [
            'null' => [Term::constant(null),false], 'false' => [Term::constant(false),true], 'zero' => [Term::constant(0),true], 'empty string' => [Term::constant(''),true],
            'array' => [Term::array([]),true], 'object' => [new Term('object', 'a'),true], 'closure' => [new Term('closure', 'a'),true], 'enum' => [new Term('enum', 'a'),true],
            'uninitialized secret' => [new Term('uninitialized', secret:true),false],
        ];
    }

    public function testNotNullKeepsTheSymbolicNullComparison(): void
    {
        $input = Term::parameter('x', 'int|null');
        $result = (new PureStep(SolverFixture::context()))->notNull($input);
        self::assertSame('binary', $result->kind);
        self::assertSame('!==', $result->literal);
        self::assertSame($input, $result->operands[0]);
        self::assertSame('constant', $result->operands[1]->kind);
        self::assertNull($result->operands[1]->literal);
        self::assertSame('bool', $result->attributes['type']);
    }

    public function testArrayReadKeepsOpenAbsenceAndRejectsIllegalKeys(): void
    {
        $step = new PureStep(SolverFixture::context());
        $state = new State();
        self::assertSame('uninitialized', $step->arrayRead(Term::array([]), Term::constant('missing'), $state)->kind);
        self::assertSame('UNKNOWN_ARRAY_KEY', $step->arrayRead(Term::array([], true), Term::constant('missing'), $state)->literal);
        self::assertSame('TypeError', $step->arrayRead(Term::array([]), Term::array([]), $state)->literal);
        self::assertSame(7, $step->arrayRead(Term::fromNative([1 => 7]), Term::constant(true), $state)->native());
    }

    public function testArrayReadRetainsUnknownContainerAndKeyExpressions(): void
    {
        $array = Term::parameter('a', 'array');
        $key = Term::parameter('key', 'string');
        $result = (new PureStep(SolverFixture::context()))->arrayRead($array, $key, new State());
        self::assertSame('array-read', $result->kind);
        self::assertSame($array, $result->operands[0]);
        self::assertSame('array-key', $result->operands[1]->kind);
        self::assertSame($key, $result->operands[1]->operands[0]);
    }

    public function testExternalPreservesCapturedInputsAndEventMetadata(): void
    {
        $input = Term::constant(null, true);
        $step = new PureStep(SolverFixture::context(configuration:new Configuration(environment:['configured' => $input])));
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        self::assertSame($input, $step->external(new Instruction('configured', 'external', $source, name:'configured'), $state));
        $event = $step->external(new Instruction('event', 'external', $source, name:'clock', attributes:['type' => 'int']), $state);
        self::assertSame('external', $event->kind);
        self::assertIsString($event->literal);
        self::assertStringStartsWith('clock:', $event->literal);
        self::assertSame(['type' => 'int','source' => 'clock','stability' => 'evaluation'], $event->attributes);
    }

    /**
     * @param scalar|null $expected Evaluated result
     */
    #[DataProvider('providerPureInstructions')]
    public function testEvaluateDispatchesPureOperations(string $op, string $name, int $previous, int|float|string|bool|null $expected): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->registers = ['a' => Term::constant(5),'b' => Term::constant(2)];
        $state->previous = $previous;
        $instruction = new Instruction('step', $op, $source, 'result', ['a','b'], $name, attributes:['left' => 7]);
        $result = (new PureStep(SolverFixture::context()))->evaluate(new CallableGraph('target', [], [], $source), $instruction, $state);
        self::assertSame($expected, $result->native());
    }

    /**
     * @return array<string,array{string,string,int,scalar|null}>
     */
    public static function providerPureInstructions(): array
    {
        return ['copy' => ['copy','',0,5],'add' => ['binary','+',0,7],'negate' => ['unary','Expr_UnaryMinus',0,-5],'cast' => ['cast','string',0,'5'],'left phi' => ['phi','',7,5],'right phi' => ['phi','',2,2],'null test' => ['not-null','',0,true],'constant fallback' => ['constant','',0,null],'target constant' => ['constant-fetch','PHP_INT_SIZE',0,8],'magic' => ['magic-constant','__FUNCTION__',0,'target']];
    }

    public function testEvaluatePreservesLiteralIdentityAndExplicitErrors(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $value = Term::constant(42, true);
        $state = new State();
        $step = new PureStep($context);
        self::assertSame($value, $step->evaluate($body, new Instruction('literal', 'constant', $source, constant:$value), $state));
        $raised = $step->evaluate($body, new Instruction('error', 'raise', $source, name:'Error'), $state);
        self::assertSame('throwable', $raised->kind);
        self::assertSame('Error', $raised->literal);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $step->evaluate($body, new Instruction('unknown', 'unknown', $source), $state)->literal);
        self::assertSame(['unknown'], array_column(array_values($context->frontiers), 'operation'));
    }

    public function testArrayReadRetainsTheConfidentialityOfAContainerOrKey(): void
    {
        $state = new State();
        $step = new PureStep(SolverFixture::context());
        $array = new Term('array', operands:['chosen' => Term::constant('value')], attributes:['open' => false], secret:true);
        $selected = $step->arrayRead($array, Term::constant('chosen'), $state);
        $keySelected = $step->arrayRead(Term::fromNative(['chosen' => 'public']), Term::constant('chosen', true), $state);
        self::assertSame('value', $selected->native());
        self::assertTrue($selected->secret);
        self::assertSame('public', $keySelected->native());
        self::assertTrue($keySelected->secret);
    }


    #[DataProvider('providerConfidentialPhi')]
    public function testPhiRetainsTheSelectedValueAndConditionConfidentiality(int $previous, bool $conditionSecret, bool $selectedSecret, string $expected): void
    {
        $state = new State();
        $state->previous = $previous;
        $state->registers['left'] = Term::constant('left', $selectedSecret);
        $state->registers['right'] = Term::constant('right', $selectedSecret);
        $state->registers['condition'] = Term::constant($previous === 7, $conditionSecret);
        $instruction = new Instruction('i', 'phi', new SourceRef('test', 'a.php', 0, 1), 'result', ['left','right','condition'], attributes:['left' => 7,'right' => 8]);
        $result = (new PureStep(SolverFixture::context()))->phi($instruction, $state);
        self::assertSame($expected, $result->native());
        self::assertSame($conditionSecret || $selectedSecret, $result->isSecret());
        self::assertSame($selectedSecret, $state->registers[$expected]->secret);
    }
    /**
     * @return iterable<string,array{int,bool,bool,string}>
     */
    public static function providerConfidentialPhi(): iterable
    {
        yield 'public true' => [7,false,false,'left'];
        yield 'public false' => [8,false,false,'right'];
        yield 'secret true' => [7,true,false,'left'];
        yield 'secret false' => [8,true,false,'right'];
        yield 'secret selected' => [7,false,true,'left'];
        yield 'both secret' => [8,true,true,'right'];
    }
}

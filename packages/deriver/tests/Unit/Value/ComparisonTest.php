<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
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
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\MatchLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Comparison::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
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
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(MatchLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return match(2){1=>"a",2=>"b",default=>"c"};}');
        self::assertSame('b', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testScalarPreservesPhpNumericStringAndStrictComparisons(): void
    {
        $comparison = new Comparison();
        self::assertSame(true, $comparison->scalar('==', 2, '2'));
        self::assertSame(false, $comparison->scalar('===', 2, '2'));
        self::assertSame(-1, $comparison->scalar('<=>', 2, 3));
    }
    public function testIdenticalComparesSharedArrayGraphsWithoutExpandingPaths(): void
    {
        $a = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $b = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $c = \Tests\Fake\ValueDocument::shared(64, Term::constant(2));
        $comparison = new Comparison();
        self::assertTrue($comparison->identical($a, $b));
        self::assertFalse($comparison->identical($a, $c));
    }
    public function testIdenticalPreservesNaNAndSignedZeroSemantics(): void
    {
        $comparison = new Comparison();
        $nan = Term::array([Term::constant(NAN)]);
        self::assertFalse($comparison->identical($nan, $nan));
        self::assertTrue($comparison->identical(Term::fromNative([-0.0]), Term::fromNative([0.0])));
    }
    public function testIdenticalPreservesKeyOrderAndScalarTypes(): void
    {
        $comparison = new Comparison();
        self::assertFalse($comparison->identical(Term::fromNative(['a' => 1,'b' => 2]), Term::fromNative(['b' => 2,'a' => 1])));
        self::assertFalse($comparison->identical(Term::fromNative([1]), Term::fromNative([1.0])));
        self::assertFalse($comparison->identical(Term::parameter('x'), Term::parameter('x')));
    }
    public function testObjectDistinguishesClosureAllocationIdentities(): void
    {
        $a = new Term('closure', 'same-body', attributes: ['identity' => 'first']);
        $b = new Term('closure', 'same-body', attributes: ['identity' => 'second']);
        $comparison = new Comparison();
        self::assertTrue($comparison->object('===', $a, $a)?->native());
        self::assertFalse($comparison->object('===', $a, $b)?->native());
        self::assertTrue($comparison->object('!==', $a, $b)?->native());
        self::assertNull($comparison->object('==', $a, $b));
    }
    public function testObjectRetainsTheConfidentialIdentityLabel(): void
    {
        $result = (new Comparison())->object('===', new Term('object', 'one', secret: true), new Term('object', 'one'));
        self::assertNotNull($result);
        self::assertTrue($result->isSecret());
    }

    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param bool|int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarComparisons')]
    public function testScalarKeepsEqualityOrderingAndNumericStringRules(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, bool|int $expected): void
    {
        self::assertSame($expected, (new Comparison())->scalar($operator, $left, $right));
    }

    /**
     * @return list<array{string, scalar|null, scalar|null, bool|int}>
     */
    public static function providerScalarComparisons(): array
    {
        return [
            ['===', 1, 1, true], ['===', 1, '1', false], ['!==', 1, '1', true], ['!==', 1, 1, false],
            ['==', 1, '1', true], ['==', 1, 'x', false], ['!=', 1, '1', false], ['!=', 1, 'x', true],
            ['<', 1, 2, true], ['<', 1, 1, false], ['<=', 1, 1, true], ['<=', 2, 1, false],
            ['>', 2, 1, true], ['>', 1, 1, false], ['>=', 1, 1, true], ['>=', 1, 2, false],
            ['<=>', 1, 2, -1], ['<=>', 2, 1, 1], ['<=>', 1, 1, 0], ['unknown', 1, 1, false],
            ['==', null, false, true], ['==', '10', '2', false], ['>', '10', '2', true],
        ];
    }

    public function testApplyPreservesSymbolicOperandsAndResultCategories(): void
    {
        $comparison = new Comparison();
        $left = Term::parameter('left', 'int');
        $right = Term::constant(3);
        $ordered = $comparison->apply('<=>', $left, $right);
        self::assertSame(['binary', '<=>', [$left, $right], ['type' => 'int']], [$ordered->kind, $ordered->literal, $ordered->operands, $ordered->attributes]);
        self::assertSame('bool', $comparison->apply('===', $left, $right)->attributes['type']);
        self::assertTrue($comparison->apply('===', Term::constant(3, true), $right)->isSecret());
        self::assertTrue($comparison->apply('===', $right, Term::constant(3, true))->isSecret());
    }

    public function testIdenticalRequiresOrderedKeysAndRecursivelyIdenticalTypes(): void
    {
        $comparison = new Comparison();
        self::assertFalse($comparison->identical(Term::parameter('a'), Term::constant(1)));
        self::assertFalse($comparison->identical(Term::constant(1), Term::parameter('b')));
        self::assertFalse($comparison->identical(Term::array([]), Term::constant(null)));
        self::assertFalse($comparison->identical(Term::fromNative(['a' => 1, 'b' => 2]), Term::fromNative(['b' => 2, 'a' => 1])));
        self::assertFalse($comparison->identical(Term::fromNative([[1]]), Term::fromNative([['1']])));
        self::assertTrue($comparison->identical(Term::fromNative([[1]]), Term::fromNative([[1]])));
        self::assertTrue($comparison->apply('!==', Term::fromNative([1]), Term::fromNative([2]))->native());
        self::assertFalse($comparison->apply('!==', Term::fromNative([1]), Term::fromNative([1]))->native());
    }

    public function testObjectUsesAllocationIdentityForObjectsEnumsAndClosures(): void
    {
        $comparison = new Comparison();
        $object = new Term('object', 'same', secret: true);
        self::assertTrue($comparison->object('===', $object, new Term('object', 'same'))?->native());
        self::assertFalse($comparison->object('!==', $object, new Term('object', 'same'))?->native());
        self::assertTrue($comparison->object('!==', $object, new Term('object', 'different'))?->native());
        self::assertTrue($comparison->object('===', $object, $object)?->isSecret());
        self::assertTrue($comparison->object('===', new Term('enum', 'E::A'), new Term('enum', 'E::A'))?->native());
        self::assertFalse($comparison->object('===', new Term('closure', 'body', attributes: ['identity' => 'a']), new Term('closure', 'body', attributes: ['identity' => 'b']))?->native());
        self::assertNull($comparison->object('===', new Term('closure', 'body'), new Term('closure', 'body')));
        self::assertNull($comparison->object('===', $object, new Term('enum', 'same')));
        self::assertNull($comparison->object('==', $object, $object));
        self::assertNull($comparison->object('===', Term::constant(1), Term::constant(1)));
    }
}

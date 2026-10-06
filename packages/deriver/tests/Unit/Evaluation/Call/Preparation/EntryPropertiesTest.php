<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Preparation;

use Deriver\Evaluation\Call\Preparation\EntryProperties;
use Deriver\Evaluation\State;
use Deriver\Exception\InvalidInputException;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\ReceiverFixture;

#[CoversClass(EntryProperties::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[Small]
final class EntryPropertiesTest extends TestCase
{
    public function testApplyWithoutPropertiesLeavesTheEntryStateUntouched(): void
    {
        [$context, $body] = ReceiverFixture::entry('free');
        $state = new State();
        (new EntryProperties($context))->apply($body, null, [], $state);
        self::assertSame([], $state->memory->cells);
        self::assertSame([], $state->memory->propertyTypes);
    }

    public function testApplyWritesDeclaredSlotsAndKeepsTheRecordOpenForUnsuppliedProperties(): void
    {
        [$context, $body] = ReceiverFixture::entry('Box::run');
        $state = new State();
        $order = Term::constant('email');
        (new EntryProperties($context))->apply($body, Term::parameter('this', 'Box'), ['order' => $order, 'table' => Term::constant('posts'), 'limit' => Term::constant(null), 'loose' => Term::array([])], $state);
        $record = $state->memory->cells['object:this'];
        self::assertSame(['Box::order', 'Base::table', 'limit', 'loose'], array_keys($record->operands));
        self::assertSame($order, $record->operands['Box::order']);
        self::assertSame('posts', $record->operands['Base::table']->native());
        self::assertTrue($record->attributes['open']);
        self::assertSame(['Box::order' => 'string', 'Base::table' => 'string', 'limit' => 'int|null', 'loose' => 'mixed'], $state->memory->propertyTypes['object:this']);
    }

    public function testApplyResolvesTheClassOfAnExplicitReceiverIdentity(): void
    {
        [$context, $body] = ReceiverFixture::entry('Box::run');
        $state = new State();
        (new EntryProperties($context))->apply($body, new Term('object', 'provided', attributes:['class' => '\\Box']), ['key' => Term::constant('k')], $state);
        self::assertSame('k', $state->memory->cells['object:provided']->operands['key']->native());
    }

    public function testApplyKeepsASymbolicValueWithinTheDeclaredType(): void
    {
        [$context, $body] = ReceiverFixture::entry('Box::run');
        $state = new State();
        (new EntryProperties($context))->apply($body, Term::parameter('this', 'Box'), ['order' => Term::parameter('order')], $state);
        $value = $state->memory->cells['object:this']->operands['Box::order'];
        self::assertFalse($value->isConcrete());
        self::assertSame('string', $value->attributes['type'] ?? null);
    }

    /**
     * @param string $symbol Entry callable
     * @param Term|null $receiver Bound receiver
     * @param array<string, Term> $properties Supplied values
     */
    #[DataProvider('providerRejected')]
    public function testApplyRejectsPropertiesNoPhpObjectCanHave(string $symbol, ?Term $receiver, array $properties, string $message): void
    {
        [$context, $body] = ReceiverFixture::entry($symbol);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new EntryProperties($context))->apply($body, $receiver, $properties, new State());
    }

    /**
     * @return iterable<string, array{string, Term|null, array<string, Term>, string}>
     */
    public static function providerRejected(): iterable
    {
        $value = ['order' => Term::constant('x')];
        yield 'function entry' => ['free', null, $value, 'Entry properties require an instance receiver: free'];
        yield 'static entry' => ['Box::make', null, $value, 'Entry properties require an instance receiver: Box::make'];
        yield 'anonymous receiver identity' => ['Box::run', new Term('object', attributes:['class' => 'Box']), $value, 'Entry properties require an instance receiver'];
        yield 'undeclared property' => ['Box::run', Term::parameter('this', 'Box'), ['missing' => Term::constant('x')], 'Entry property is not a declared instance property: Box::$missing'];
        yield 'static property' => ['Box::run', Term::parameter('this', 'Box'), ['count' => Term::constant(1)], 'Entry property is not a declared instance property: Box::$count'];
        yield 'scalar type violation' => ['Box::run', Term::parameter('this', 'Box'), ['order' => Term::constant(1)], 'Entry property value does not satisfy the declared type string: Box::$order'];
        yield 'nullable type violation' => ['Box::run', Term::parameter('this', 'Box'), ['limit' => Term::constant('ten')], 'Entry property value does not satisfy the declared type int|null: Box::$limit'];
        yield 'union type violation' => ['Box::run', Term::parameter('this', 'Box'), ['key' => Term::constant(1.5)], 'Entry property value does not satisfy the declared type int|string: Box::$key'];
    }
}

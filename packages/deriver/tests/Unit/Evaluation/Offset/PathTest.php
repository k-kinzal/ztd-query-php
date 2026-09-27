<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\ProtocolAccess;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Offset\StringAccess;
use Deriver\Evaluation\Offset\Strings;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Result\Frontier;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
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
use Deriver\Value\Arrays;
use Deriver\Value\IntegerConversion;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Evaluation\Offset\Path
 */
#[CoversClass(Path::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Address::class)]
#[UsesClass(ProtocolAccess::class)]
#[UsesClass(Reader::class)]
#[UsesClass(StringAccess::class)]
#[UsesClass(Strings::class)]
#[UsesClass(State::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableCompiler::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class PathTest extends TestCase
{
    public function testChainPreservesTheOriginalContainerAndNestedKeys(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $state->offsets['nested'] = new Address('slot', Term::constant('y'));
        $chain = $path->chain($state, 'nested');
        self::assertSame($base, $chain['base']);
        self::assertSame(['slot', 'nested'], $chain['offsets']);
    }
    public function testReadDoesNotAutovivifyNullContainers(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $read = new Instruction('read', 'read', $body->source, 'result', ['slot']);
        $result = $path->read($state, $read);
        self::assertInstanceOf(Term::class, $result);
        self::assertNull($result->native());
        self::assertNull($state->memory->read($base)->native());
    }
    public function testLocateRetainsArrayCreationBeforeAnInvalidKey(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $state->offsets['slot'] = new Address('base', Term::array([]));
        $result = $path->locate($body, $state, $instruction);
        self::assertInstanceOf(Term::class, $result);
        self::assertSame('TypeError', $result->literal);
        self::assertSame([], $state->memory->read($base)->native());
    }
    public function testPrepareRejectsScalarMutationWithoutChangingTheScalar(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $state->memory->write($base, Term::constant(4));
        self::assertSame('Error', $path->prepare($body, $state, $base, $instruction, true)->literal);
        self::assertSame(4, $state->memory->read($base)->native());
    }
    public function testCreateChecksDirectPropertyTypesBeforeArrayCreation(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $state->memory->cells['object'] = Term::array(['field' => Term::constant(null)]);
        $state->memory->propertyTypes['object']['field'] = 'int|null';
        $address = new Location('object', ['field']);
        self::assertSame('TypeError', $path->create($body, $state, $address, Term::constant(null), $instruction)->literal);
        self::assertNull($state->memory->read($address)->native());
    }

    public function testChainRetainsUnknownBasesAndDirectLocations(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $path = new Path($context);
        $missing = $path->chain($state, 'absent');
        $known = new Location('root', ['existing']);
        $state->addresses['known'] = $known;
        self::assertTrue($missing['base']->unknown);
        self::assertSame([], $missing['offsets']);
        self::assertSame(['base' => $known, 'offsets' => []], $path->chain($state, 'known'));
    }

    /**
     * @param Term $container Root value
     * @param string $operation Read mode
     * @param string $kind Expected value category
     * @param mixed $literal Expected payload
     * @param list<string> $reasons Expected diagnostics
     */
    #[DataProvider('providerReadCases')]
    public function testReadStopsAtErrorsAndHonorsSilentAbsence(Term $container, string $operation, string $kind, mixed $literal, array $reasons): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate($container);
        $state->addresses['base'] = $base;
        $state->offsets['first'] = new Address('base', Term::constant('x'));
        $state->offsets['second'] = new Address('first', Term::constant('y'));
        $result = (new Path($context))->read($state, new Instruction('read', $operation, $body->source, 'result', ['second']));
        self::assertInstanceOf(Term::class, $result);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertSame($reasons, array_column($context->frontiers, 'code'));
        self::assertSame($container, $state->memory->read($base));
    }

    /**
     * @return iterable<string, array{Term, string, string, mixed, list<string>}>
     */
    public static function providerReadCases(): iterable
    {
        yield 'typed absence throws before offsets' => [new Term('uninitialized', attributes: ['type' => 'array']), 'read', 'throwable', 'Error', []];
        yield 'typed silent absence' => [new Term('uninitialized', attributes: ['type' => 'array']), 'read-silent', 'constant', null, []];
        yield 'untyped absence warns' => [new Term('uninitialized'), 'read', 'constant', null, ['PHP_WARNING']];
        yield 'mixed absence warns' => [new Term('uninitialized', attributes: ['type' => 'mixed']), 'read', 'constant', null, ['PHP_WARNING']];
        yield 'nested array' => [Term::array(['x' => Term::array(['y' => Term::constant(19)])]), 'read', 'constant', 19, []];
        yield 'string invalid key stops' => [Term::constant('text'), 'read', 'throwable', 'TypeError', []];
        yield 'unknown container stops' => [Term::parameter('input'), 'read', 'opaque', 'OFFSET_OPERATION', []];
        yield 'plain object stops' => [new Term('object', 'one', attributes: ['class' => 'stdClass']), 'read', 'throwable', 'Error', []];
    }

    public function testReadDefersAnOverloadedElementWithTheRemainingChain(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $receiver = new Term('object', 'bag', attributes: ['class' => 'UnknownBag']);
        $key = Term::constant('original', true);
        $state = new State();
        $state->addresses['base'] = $state->memory->allocate(Term::array(['first' => $receiver]));
        $state->offsets['first'] = new Address('base', Term::constant('first'));
        $state->offsets['second'] = new Address('first', $key);
        $state->offsets['third'] = new Address('second', Term::constant(3));
        $result = (new Path($context))->read($state, new Instruction('read', 'read', $body->source, 'result', ['third']));
        self::assertInstanceOf(ProtocolAccess::class, $result);
        self::assertSame($receiver, $result->receiver);
        self::assertSame($key, $result->key);
        self::assertSame(['third'], $result->remaining);
    }

    /**
     * @param Term $key Original key
     * @param int|string $normalized PHP array key
     * @param list<string> $reasons Expected diagnostics
     */
    #[DataProvider('providerArrayKeys')]
    public function testLocateNormalizesArrayKeysAndRetainsTheOriginalRoot(Term $key, int|string $normalized, array $reasons): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::array(['outer' => Term::array([])]));
        $state->addresses['base'] = new Location($base->root, ['outer']);
        $state->offsets['offset'] = new Address('base', $key);
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['offset', 'value']));
        self::assertInstanceOf(Location::class, $result);
        self::assertSame($base->root, $result->root);
        self::assertSame(['outer', $normalized], $result->path);
        self::assertSame($result, $state->addresses['offset']);
        self::assertSame($key, $state->offsets['offset']->key);
        self::assertSame(['outer' => []], $state->memory->read($base)->native());
        self::assertSame($reasons, array_column($context->frontiers, 'code'));
    }

    /**
     * @return iterable<string, array{Term, int|string, list<string>}>
     */
    public static function providerArrayKeys(): iterable
    {
        yield 'integer' => [Term::constant(4), 4, []];
        yield 'negative integer' => [Term::constant(-2), -2, []];
        yield 'string' => [Term::constant('name'), 'name', []];
        yield 'numeric string' => [Term::constant('4'), 4, []];
        yield 'leading zero string' => [Term::constant('04'), '04', []];
        yield 'null' => [Term::constant(null), '', []];
        yield 'true' => [Term::constant(true), 1, []];
        yield 'false' => [Term::constant(false), 0, []];
        yield 'integer float' => [Term::constant(2.0), 2, []];
        yield 'fractional float' => [Term::constant(2.5), 2, ['PHP_WARNING']];
    }

    public function testLocateAppendStoresTheResolvedKeyForSubsequentReads(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::array([4 => Term::constant('existing')]));
        $state->addresses['base'] = $base;
        $state->offsets['append'] = new Address('base', null);
        $path = new Path($context);
        $result = $path->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['append', 'value']));
        self::assertInstanceOf(Location::class, $result);
        $state->memory->write($result, Term::constant('new'));
        $read = $path->read($state, new Instruction('read', 'read', $body->source, 'read', ['append']));
        self::assertInstanceOf(Term::class, $read);
        self::assertSame('new', $read->native());
        self::assertSame([5], $result->path);
        self::assertSame('base', $state->offsets['append']->parent);
        self::assertSame(5, $state->offsets['append']->key?->native());
        self::assertSame([4 => 'existing', 5 => 'new'], $state->memory->read($base)->native());
    }

    public function testLocatePreservesIntermediateArraysWhenTheLastKeyFails(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['first'] = new Address('base', Term::constant('created'));
        $state->offsets['second'] = new Address('first', Term::array([]));
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['second', 'value']));
        self::assertInstanceOf(Term::class, $result);
        self::assertSame('TypeError', $result->literal);
        self::assertSame(['created' => []], $state->memory->read($base)->native());
        self::assertSame(['created'], $state->addresses['first']->path);
        self::assertArrayNotHasKey('second', $state->addresses);
    }

    public function testLocateUnsetDoesNotCreateMissingIntermediateArrays(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::array([]));
        $state->addresses['base'] = $base;
        $state->offsets['first'] = new Address('base', Term::constant('absent'));
        $state->offsets['second'] = new Address('first', Term::constant('nested'));
        $result = (new Path($context))->locate($body, $state, new Instruction('unset', 'unset', $body->source, 'result', ['second']));
        self::assertInstanceOf(Term::class, $result);
        self::assertNull($result->native());
        self::assertSame([], $state->memory->read($base)->native());
        self::assertSame([], $context->frontiers);
        self::assertArrayNotHasKey('second', $state->addresses);
    }

    public function testLocateReturnsStringStorageWithoutCoercingTheOriginalKey(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::array(['text' => Term::constant('abc')]));
        $key = Term::constant('-1');
        $state->addresses['base'] = $base;
        $state->offsets['first'] = new Address('base', Term::constant('text'));
        $state->offsets['second'] = new Address('first', $key);
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['second', 'value']));
        self::assertInstanceOf(StringAccess::class, $result);
        self::assertSame($base->root, $result->container->root);
        self::assertSame(['text'], $result->container->path);
        self::assertSame($key, $result->key);
        self::assertSame(['text' => 'abc'], $state->memory->read($base)->native());
    }

    /**
     * @param Term|null $key Evaluated offset or append
     * @param string $kind Expected outcome category
     * @param string $literal Expected error or boundary
     */
    #[DataProvider('providerNestedStringKeys')]
    public function testLocateRejectsNestedStringMutationWithoutChangingStorage(?Term $key, string $kind, string $literal): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate(Term::constant('abc'));
        $state->addresses['base'] = $base;
        $state->offsets['first'] = new Address('base', $key);
        $state->offsets['second'] = new Address('first', Term::constant(0));
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['second', 'value']));
        self::assertInstanceOf(Term::class, $result);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertSame('abc', $state->memory->read($base)->native());
        self::assertArrayNotHasKey('first', $state->addresses);
    }

    /**
     * @return iterable<string, array{Term|null, string, string}>
     */
    public static function providerNestedStringKeys(): iterable
    {
        yield 'valid byte' => [Term::constant(0), 'throwable', 'Error'];
        yield 'invalid byte key' => [Term::constant('bad'), 'throwable', 'TypeError'];
        yield 'append' => [null, 'throwable', 'Error'];
        yield 'unknown key' => [Term::parameter('key'), 'opaque', 'OFFSET_OPERATION'];
    }

    public function testLocatePreservesOverloadedReceiversAndUnevaluatedSuffixes(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $receiver = new Term('object', 'bag', attributes: ['class' => 'UnknownBag']);
        $key = Term::array(['uncoerced' => Term::constant(1)]);
        $state->addresses['base'] = $state->memory->allocate($receiver);
        $state->offsets['first'] = new Address('base', $key);
        $state->offsets['second'] = new Address('first', Term::constant('remaining'));
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['second', 'value']));
        self::assertInstanceOf(ProtocolAccess::class, $result);
        self::assertSame($receiver, $result->receiver);
        self::assertSame($key, $result->key);
        self::assertSame(['second'], $result->remaining);
        self::assertArrayNotHasKey('first', $state->addresses);
    }

    public function testLocateRetainsUnknownKeyDependenciesAndConfidentiality(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $container = Term::array([]);
        $key = new Term('parameter', 'key', secret: true);
        $state->addresses['base'] = $state->memory->allocate($container);
        $state->offsets['offset'] = new Address('base', $key);
        $result = (new Path($context))->locate($body, $state, new Instruction('write', 'write', $body->source, 'result', ['offset', 'value']));
        self::assertInstanceOf(Term::class, $result);
        self::assertSame('OFFSET_OPERATION', $result->literal);
        self::assertSame([$container, $key], $result->operands);
        self::assertTrue($result->isSecret());
        self::assertArrayNotHasKey('offset', $state->addresses);
    }

    /**
     * @param Term $container Previous storage
     * @param bool $create Whether creation is permitted
     * @param string $kind Expected category
     * @param mixed $literal Expected payload
     * @param bool $created Whether an empty array replaces storage
     * @param list<string> $reasons Expected diagnostics
     */
    #[DataProvider('providerContainers')]
    public function testPrepareChangesOnlyLegalAutovivifiedContainers(Term $container, bool $create, string $kind, mixed $literal, bool $created, array $reasons): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $base = $state->memory->allocate($container);
        $result = (new Path($context))->prepare($body, $state, $base, new Instruction('offset', 'write', $body->source), $create);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertEquals($created ? Term::array([]) : $container, $state->memory->read($base));
        self::assertSame($reasons, array_column($context->frontiers, 'code'));
    }

    /**
     * @return iterable<string, array{Term, bool, string, mixed, bool, list<string>}>
     */
    public static function providerContainers(): iterable
    {
        yield 'array' => [Term::array([]), true, 'array', null, false, []];
        yield 'string' => [Term::constant('abc'), true, 'constant', 'abc', false, []];
        yield 'null created' => [Term::constant(null), true, 'array', null, true, []];
        yield 'false created' => [Term::constant(false), true, 'array', null, true, ['PHP_WARNING']];
        yield 'uninitialized created' => [new Term('uninitialized'), true, 'array', null, true, []];
        yield 'null unset' => [Term::constant(null), false, 'constant', null, false, []];
        yield 'false unset' => [Term::constant(false), false, 'constant', null, false, ['PHP_WARNING']];
        yield 'uninitialized unset' => [new Term('uninitialized'), false, 'constant', null, false, []];
        yield 'true' => [Term::constant(true), true, 'throwable', 'Error', false, []];
        yield 'integer zero' => [Term::constant(0), true, 'throwable', 'Error', false, []];
        yield 'float' => [Term::constant(1.5), true, 'throwable', 'Error', false, []];
        yield 'plain object' => [new Term('object', 'one', attributes: ['class' => 'stdClass']), true, 'throwable', 'Error', false, []];
        yield 'closure' => [new Term('closure', 'one'), true, 'throwable', 'Error', false, []];
        yield 'enum' => [new Term('enum', 'A'), true, 'throwable', 'Error', false, []];
        yield 'possible ArrayAccess' => [new Term('object', 'bag'), true, 'object', 'bag', false, []];
        yield 'symbolic' => [Term::parameter('input'), true, 'opaque', 'OFFSET_OPERATION', false, []];
    }

    public function testCreateAppliesTheTypesOfPropertiesSharingAnEscapedReference(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $shared = $state->memory->allocate(Term::constant(null));
        $state->memory->cells['object'] = Term::array(['field' => new Term('cell', $shared->root)]);
        $state->memory->propertyTypes['object']['field'] = 'int|null';
        $result = (new Path($context))->create($body, $state, $shared, Term::constant(null), new Instruction('offset', 'write', $body->source));
        self::assertSame('throwable', $result->kind);
        self::assertSame('TypeError', $result->literal);
        self::assertNull($state->memory->read($shared)->native());
        self::assertNull($state->memory->read(new Location('object', ['field']))->native());
    }

    public function testCreateAllowsNullableArrayProperties(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->cells['object'] = Term::array(['field' => Term::constant(null)]);
        $state->memory->propertyTypes['object']['field'] = 'array|null';
        $address = new Location('object', ['field']);
        $result = (new Path($context))->create($body, $state, $address, Term::constant(null), new Instruction('offset', 'write', $body->source));
        self::assertSame([], $result->native());
        self::assertSame([], $state->memory->read($address)->native());
        self::assertSame(['object' => ['field' => 'array|null']], $state->memory->propertyTypes);
    }
}

<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration\Traits;

use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassConstant;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants;
use Deriver\Deriver\Lowerer\Declaration\Traits\Members;
use Deriver\Deriver\Lowerer\Declaration\Traits\PropertyScope;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Lowerer\Declaration\Traits\Composition
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Composition::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(LexicalConstants::class)]
#[UsesClass(Members::class)]
#[UsesClass(PropertyScope::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Literal::class)]
#[Small]
final class CompositionTest extends TestCase
{
    public function testComposeImportsNestedPrivateMembersIntoTheConsumer(): void
    {
        $source = '<?php trait U{private $x=4;function f(){return $this->x;}}trait T{use U;}class B{use T;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertTrue($index->classes()['b']->composed);
    }
    public function testImportPreservesClassMethodPrecedence(): void
    {
        $source = '<?php trait T{function f(){return 1;}}class B{use T;function f(){return 2;}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame([], $index->diagnostics());
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertSame(['T'], $index->classes()['b']->traits);
    }
    public function testPropertiesCreatesSeparatePrivateSlotsForEachConsumer(): void
    {
        $source = '<?php trait T{private $x=1;}class A{use T;}class B{use T;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame('A', $index->classes()['a']->properties['x']->className);
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
    }
    public function testConstantsRetainsUnevaluatedInitializersInConsumerScope(): void
    {
        $source = '<?php trait T{const C=self::D;}class B{use T;const D=4;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $constant = $index->constant('B::C');
        self::assertNotNull($constant);
        self::assertSame('B', $constant->className);
    }
    public function testComposeRecordsMissingTraitsAndCyclesAtTheConsumingSource(): void
    {
        $source = '<?php class Consumer {use Missing;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $missing = new Index($snapshotId, $trees);
        self::assertCount(1, $missing->diagnostics());
        self::assertSame('INCOMPLETE_SOURCE', $missing->diagnostics()[0]->code);
        self::assertSame('missing-trait:Missing', $missing->diagnostics()[0]->operation);
        self::assertSame('fixture.php', $missing->diagnostics()[0]->at->path);
        $source2 = '<?php trait Recursive {use Recursive;}';
        $snapshotId1 = 'test';
        $input1 = new Sources([new Source('fixture.php', $source2)]);
        $profile1 = new TargetProfile();
        $parser1 = new Parser(new ParserConfig($profile1->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input1, false));
        $cyclic = new Index($snapshotId1, $trees1);
        self::assertCount(1, $cyclic->diagnostics());
        self::assertSame('INVALID_PROGRAM', $cyclic->diagnostics()[0]->code);
        self::assertSame('cyclic-trait-composition:Recursive', $cyclic->diagnostics()[0]->operation);
    }
    public function testComposeRemembersCaseInsensitiveVisitsAndLeavesUnknownTargetsUntouched(): void
    {
        $source = '<?php trait Shared{} class Consumer{use Shared;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $composition = new Composition($index);
        $composition->compose('CONSUMER');
        $consumer = $index->classIndex['consumer'];
        $composition->compose('consumer');
        $composition->compose('Missing');
        self::assertSame(['shared' => true, 'consumer' => true], $composition->done);
        self::assertSame($consumer, $index->classIndex['consumer']);
        self::assertSame([], $index->diagnostics());
    }
    public function testComposeWaitsForBothDeclarationAndCapturedClassSyntax(): void
    {
        $source = '<?php class Consumer{}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        unset($index->classSources['consumer']);
        $composition = new Composition($index);
        $before = $index->classIndex['consumer'];
        $composition->compose('Consumer');
        self::assertSame([], $composition->done);
        self::assertSame($before, $index->classIndex['consumer']);
    }
    public function testImportPreservesClassContractsAndRebindsCopiedMethods(): void
    {
        $source1 = '<?php declare(strict_types=1); interface Contract{} class Base{} trait Shared{function Run(){return __TRAIT__;}} final class Consumer extends Base implements Contract{use Shared; public const OWN=7;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $before = $index->classIndex['consumer'];
        $composition = new Composition($index);
        $composition->import($before, $index->classSources['consumer']);
        $after = $index->classIndex['consumer'];
        self::assertSame('Consumer', $after->name);
        self::assertSame('Base', $after->parent);
        self::assertSame(['Contract'], $after->interfaces);
        self::assertSame(['Shared'], $after->traits);
        self::assertTrue($after->final);
        self::assertFalse($after->abstract);
        self::assertFalse($after->interface);
        self::assertFalse($after->readonly);
        self::assertFalse($after->enum);
        self::assertTrue($after->composed);
        self::assertSame($before->constants, $after->constants);
        self::assertSame($before->constantDeclarations, $after->constantDeclarations);
        $source = $index->declarations['consumer::run'];
        self::assertSame('Consumer', $source->className);
        self::assertSame('Consumer::run', $source->symbol);
        self::assertSame('fixture.php', $source->path);
        self::assertTrue($source->strict);
        self::assertNotSame($index->declarations['shared::run']->node, $source->node);
    }
    #[DataProvider('providerConsumerFlags')]
    public function testImportRetainsReadonlyAbstractAndEnumDeclarations(string $source, bool $abstract, bool $readonly, bool $enum): void
    {
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $consumer = $index->classIndex['consumer'];
        self::assertSame($abstract, $consumer->abstract);
        self::assertSame($readonly, $consumer->readonly);
        self::assertSame($enum, $consumer->enum);
        self::assertTrue($consumer->composed);
        self::assertSame(['run' => 'Consumer::run'], $consumer->methods);
        self::assertSame([], $index->diagnostics());
    }
    /**
     * @return iterable<string,array{string,bool,bool,bool}>
     */
    public static function providerConsumerFlags(): iterable
    {
        yield 'abstract' => ['<?php trait Shared{function run(){}} abstract class Consumer{use Shared;}', true, false, false];
        yield 'readonly' => ['<?php trait Shared{function run(){}} readonly class Consumer{use Shared;}', false, true, false];
        yield 'enum' => ['<?php trait Shared{function run(){}} enum Consumer{use Shared;case Ready;}', false, false, true];
    }
    public function testPropertiesRetainsTypesVisibilityStaticReadonlyAndInitializerAbsence(): void
    {
        $source = '<?php trait Shared{private int $missing; protected static string $label="value"; public readonly int $id;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $trait = $index->classIndex['shared'];
        $properties = (new Composition($index))->properties($trait, 'Consumer');
        self::assertSame(['missing', 'label', 'id'], array_keys($properties));
        self::assertSame(['Consumer', 'Consumer', 'Consumer'], array_column($properties, 'className'));
        self::assertSame(['int', 'string', 'int'], array_column($properties, 'type'));
        self::assertSame([Visibility::Private, Visibility::Protected, Visibility::Public], array_column($properties, 'visibility'));
        self::assertSame([false, true, false], array_column($properties, 'static'));
        self::assertSame([false, false, true], array_column($properties, 'readonly'));
        self::assertNull($properties['missing']->default);
        self::assertNull($properties['id']->default);
        self::assertNotNull($properties['label']->default);
        self::assertNotSame($trait->properties['label']->default, $properties['label']->default);
        self::assertSame('Consumer', $properties['label']->default->className);
        self::assertSame('Shared', $trait->properties['missing']->className);
    }
    public function testConstantsCopiesUnevaluatedSourceIdentityAndPreservesExistingConsumerDefinitions(): void
    {
        $source = '<?php declare(strict_types=1); trait Shared{protected const int COPIED=7; const OWN=9;} class Consumer{const OWN=11;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $original = $index->constantSources['consumer::OWN'];
        $composition = new Composition($index);
        $composition->constants($index->classIndex['shared'], 'Consumer');
        $copied = $index->constantSources['consumer::COPIED'];
        self::assertSame('consumer::COPIED', $copied->symbol);
        self::assertSame($index->constantSources['shared::COPIED']->node, $copied->node);
        self::assertSame('fixture.php', $copied->path);
        self::assertSame('Consumer', $copied->className);
        self::assertTrue($copied->strict);
        self::assertSame($original, $index->constantSources['consumer::OWN']);
    }
    public function testImportRetainsConstantVisibilityTypesAndConsumerOwnership(): void
    {
        $source = '<?php trait Shared{protected const int Value=7;} class Consumer{use Shared;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $constant = $index->classIndex['consumer']->constantDeclarations['Value'];
        self::assertSame('Consumer', $constant->className);
        self::assertSame('Value', $constant->name);
        self::assertSame(Visibility::Protected, $constant->visibility);
        self::assertSame('int', $constant->type);
        self::assertFalse($constant->enum);
        self::assertSame($index->classIndex['shared']->constants['Value'], $index->classIndex['consumer']->constants['Value']);
    }
}

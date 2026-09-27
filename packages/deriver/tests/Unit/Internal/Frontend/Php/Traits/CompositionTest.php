<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Traits;

use Deriver\Internal\Frontend\Php\Traits\Composition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FrontendFixture;

/**
 * @covers \Deriver\Internal\Frontend\Php\Traits\Composition
 */
#[CoversClass(Composition::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\PropertyScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CompositionTest extends TestCase
{
    public function testComposeImportsNestedPrivateMembersIntoTheConsumer(): void
    {
        $index = FrontendFixture::index('<?php trait U{private $x=4;function f(){return $this->x;}}trait T{use U;}class B{use T;}');
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertTrue($index->classes()['b']->composed);
    }
    public function testImportPreservesClassMethodPrecedence(): void
    {
        $index = FrontendFixture::index('<?php trait T{function f(){return 1;}}class B{use T;function f(){return 2;}}');
        self::assertSame([], $index->diagnostics());
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertSame(['T'], $index->classes()['b']->traits);
    }
    public function testPropertiesCreatesSeparatePrivateSlotsForEachConsumer(): void
    {
        $index = FrontendFixture::index('<?php trait T{private $x=1;}class A{use T;}class B{use T;}');
        self::assertSame('A', $index->classes()['a']->properties['x']->className);
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
    }
    public function testConstantsRetainsUnevaluatedInitializersInConsumerScope(): void
    {
        $index = FrontendFixture::index('<?php trait T{const C=self::D;}class B{use T;const D=4;}');
        $constant = $index->constant('B::C');
        self::assertNotNull($constant);
        self::assertSame('B', $constant->className);
    }

    public function testComposeRecordsMissingTraitsAndCyclesAtTheConsumingSource(): void
    {
        $missing = FrontendFixture::index('<?php class Consumer {use Missing;}');
        self::assertCount(1, $missing->diagnostics());
        self::assertSame('INCOMPLETE_SOURCE', $missing->diagnostics()[0]->code);
        self::assertSame('missing-trait:Missing', $missing->diagnostics()[0]->operation);
        self::assertSame('fixture.php', $missing->diagnostics()[0]->at->path);
        $cyclic = FrontendFixture::index('<?php trait Recursive {use Recursive;}');
        self::assertCount(1, $cyclic->diagnostics());
        self::assertSame('INVALID_PROGRAM', $cyclic->diagnostics()[0]->code);
        self::assertSame('cyclic-trait-composition:Recursive', $cyclic->diagnostics()[0]->operation);
    }

    public function testComposeRemembersCaseInsensitiveVisitsAndLeavesUnknownTargetsUntouched(): void
    {
        $index = FrontendFixture::index('<?php trait Shared{} class Consumer{use Shared;}');
        $composition = new Composition($index, 'version');
        $composition->compose('CONSUMER');
        $consumer = $index->classIndex['consumer'];
        $composition->compose('consumer');
        $composition->compose('Missing');
        self::assertSame(['shared' => true,'consumer' => true], $composition->done);
        self::assertSame($consumer, $index->classIndex['consumer']);
        self::assertSame([], $index->diagnostics());
    }

    public function testComposeWaitsForBothDeclarationAndCapturedClassSyntax(): void
    {
        $index = FrontendFixture::index('<?php class Consumer{}');
        unset($index->classSources['consumer']);
        $composition = new Composition($index, 'version');
        $before = $index->classIndex['consumer'];
        $composition->compose('Consumer');
        self::assertSame([], $composition->done);
        self::assertSame($before, $index->classIndex['consumer']);
    }

    public function testImportPreservesClassContractsAndRebindsCopiedMethods(): void
    {
        $index = FrontendFixture::index('<?php declare(strict_types=1); interface Contract{} class Base{} trait Shared{function Run(){return __TRAIT__;}} final class Consumer extends Base implements Contract{use Shared; public const OWN=7;}');
        $before = $index->classIndex['consumer'];
        $composition = new Composition($index, 'new-version');
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
        $index = FrontendFixture::index($source);
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
        yield 'abstract' => ['<?php trait Shared{function run(){}} abstract class Consumer{use Shared;}',true,false,false];
        yield 'readonly' => ['<?php trait Shared{function run(){}} readonly class Consumer{use Shared;}',false,true,false];
        yield 'enum' => ['<?php trait Shared{function run(){}} enum Consumer{use Shared;case Ready;}',false,false,true];
    }

    public function testPropertiesRetainsTypesVisibilityStaticReadonlyAndInitializerAbsence(): void
    {
        $index = FrontendFixture::index('<?php trait Shared{private int $missing; protected static string $label="value"; public readonly int $id;}');
        $trait = $index->classIndex['shared'];
        $properties = (new Composition($index, 'version'))->properties($trait, 'Consumer');
        self::assertSame(['missing','label','id'], array_keys($properties));
        self::assertSame(['Consumer','Consumer','Consumer'], array_column($properties, 'className'));
        self::assertSame(['int','string','int'], array_column($properties, 'type'));
        self::assertSame(['private','protected','public'], array_column($properties, 'visibility'));
        self::assertSame([false,true,false], array_column($properties, 'static'));
        self::assertSame([false,false,true], array_column($properties, 'readonly'));
        self::assertNull($properties['missing']->default);
        self::assertNull($properties['id']->default);
        self::assertNotNull($properties['label']->default);
        self::assertNotSame($trait->properties['label']->default, $properties['label']->default);
        self::assertSame('Consumer', $properties['label']->default->className);
        self::assertSame('Shared', $trait->properties['missing']->className);
    }

    public function testConstantsCopiesUnevaluatedSourceIdentityAndPreservesExistingConsumerDefinitions(): void
    {
        $index = FrontendFixture::index('<?php declare(strict_types=1); trait Shared{protected const int COPIED=7; const OWN=9;} class Consumer{const OWN=11;}');
        $original = $index->constantSources['consumer::OWN'];
        $composition = new Composition($index, 'source-version');
        $composition->constants($index->classIndex['shared'], 'Consumer');
        $copied = $index->constantSources['consumer::COPIED'];
        self::assertSame('consumer::COPIED', $copied->symbol);
        self::assertSame($index->constantSources['shared::COPIED']->node, $copied->node);
        self::assertSame('fixture.php', $copied->path);
        self::assertSame('Consumer', $copied->className);
        self::assertTrue($copied->strict);
        self::assertSame('source-version', $copied->cacheSalt);
        self::assertSame($original, $index->constantSources['consumer::OWN']);
    }

    public function testImportRetainsConstantVisibilityTypesAndConsumerOwnership(): void
    {
        $index = FrontendFixture::index('<?php trait Shared{protected const int Value=7;} class Consumer{use Shared;}');
        $constant = $index->classIndex['consumer']->constantDeclarations['Value'];
        self::assertSame('Consumer', $constant->className);
        self::assertSame('Value', $constant->name);
        self::assertSame('protected', $constant->visibility);
        self::assertSame('int', $constant->type);
        self::assertFalse($constant->enum);
        self::assertSame($index->classIndex['shared']->constants['Value'], $index->classIndex['consumer']->constants['Value']);
    }
}

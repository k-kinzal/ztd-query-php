<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\CallSiteIndex;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
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
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Lowerer\Declaration\CallSiteIndex
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(CallSiteIndex::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[Small]
final class CallSiteIndexTest extends TestCase
{
    public function testOwnersIndexesNestedClosuresAsSeparateOwners(): void
    {
        $source = '<?php function target(){return fn()=>sink(1);}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $owners = (new CallSiteIndex($index))->owners('sink');
        self::assertCount(1, $owners);
        self::assertStringStartsWith('closure:fixture.php:', $owners[0]);
        self::assertSame(0, $index->graphCount());
    }
    public function testContainsDoesNotAttributeANestedFunctionCallToItsParent(): void
    {
        $source1 = '<?php function target(){function nested(){sink(1);}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $source = $index->declarations['target'];
        $pending = [];
        self::assertFalse((new CallSiteIndex($index))->contains($source->node, 'sink', $source, $pending, true));
    }
    #[DataProvider('providerNamespacedCalls')]
    public function testOwnersFindsResolvedNamespaceAndImportedNamesWithoutCompilingBodies(string $source, string $selector): void
    {
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['N\target'], (new CallSiteIndex($index))->owners($selector));
        self::assertSame(0, $index->graphCount());
    }
    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerNamespacedCalls(): iterable
    {
        yield 'implicit namespace' => ['<?php namespace N;function target(){sink(1);}', 'N\sink'];
        yield 'case-insensitive qualified selector' => ['<?php namespace N;function target(){sink(1);}', '\n\SINK'];
        yield 'qualified syntax' => ['<?php namespace N;function target(){\N\sink(1);}', 'N\sink'];
        yield 'function import' => ['<?php namespace N;use function M\sink as alias;function target(){alias(1);}', 'M\sink'];
        yield 'namespace-relative syntax' => ['<?php namespace N;function target(){namespace\sink(1);}', 'N\sink'];
        yield 'ordinary method name' => ['<?php namespace N;function target($x){$x->sink(1);}', 'SINK'];
    }
    public function testFallbackOnlyMatchesUnqualifiedFunctionNames(): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $calls = new CallSiteIndex($index);
        self::assertTrue($calls->fallback(new FuncCall(new Name('sink')), 'sink'));
        self::assertFalse($calls->fallback(new FuncCall(new Name('Local\sink')), 'sink'));
    }
    #[DataProvider('providerCreations')]
    public function testCreationMatchesConstructorSelectors(string $class, string $selector, bool $expected): void
    {
        $source = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        self::assertSame($expected, (new CallSiteIndex(new Index($snapshotId, $trees)))->creation(new Name($class), $selector));
    }
    /**
     * @return iterable<string,array{string,string,bool}>
     */
    public static function providerCreations(): iterable
    {
        yield 'wildcard' => ['N\Repo', '*', true];
        yield 'constructor' => ['N\Repo', '\n\REPO::__Construct', true];
        yield 'class name' => ['N\Repo', 'N\Repo', false];
        yield 'other class' => ['N\Repo', 'N\Other::__construct', false];
        yield 'lexical class' => ['self', 'N\Repo::__construct', true];
        yield 'lexical class by function name' => ['self', 'sink', false];
    }
    public function testOwnersFindsCreationsWithoutCompilingBodies(): void
    {
        $source = '<?php namespace N;class Repo {} function target(){return new Repo;} function other($c){return new $c;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['N\target'], (new CallSiteIndex($index))->owners('N\Repo::__construct'));
        self::assertSame(['N\target'], (new CallSiteIndex($index))->owners('*'));
        self::assertSame(0, $index->graphCount());
    }
}

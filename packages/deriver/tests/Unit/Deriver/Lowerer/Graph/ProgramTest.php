<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Graph;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ProgramTest extends TestCase
{
    public function testCallablePreservesTheSemanticContract(): void
    {
        self::assertContains(Program::class, class_implements(Index::class));
    }
    public function testClassesIncludesDeclarationsBeforeAnyGraphIsDemanded(): void
    {
        $source = '<?php class A {}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['a'], array_keys($index->classes()));
        self::assertSame(0, $index->graphCount());
    }
    public function testSymbolsReturnsStableSourceIdentities(): void
    {
        $source = '<?php function Z(){} function A(){}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['A', 'Z', 'script:fixture.php'], $index->symbols());
    }
    public function testDiagnosticsDistinguishesConflictingDeclarationsFromAnEmptyIndex(): void
    {
        $source = '<?php function same(){} function SAME(){}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertCount(1, $index->diagnostics());
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
        self::assertSame('duplicate:SAME', $index->diagnostics()[0]->operation);
        self::assertSame('fixture.php', $index->diagnostics()[0]->at->path);
        $source2 = '<?php';
        $snapshotId1 = 'test';
        $input1 = new Sources([new Source('fixture.php', $source2)]);
        $profile1 = new TargetProfile();
        $parser1 = new Parser(new ParserConfig($profile1->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input1, false));
        self::assertSame([], (new Index($snapshotId1, $trees1))->diagnostics());
    }
    public function testGraphCountCountsOnlyDemandedBodiesAndReusesTheGraph(): void
    {
        $source = '<?php function target(){} function other(){}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(0, $index->graphCount());
        $first = $index->callable('target');
        self::assertNotNull($first);
        self::assertSame($first, $index->callable('TARGET'));
        self::assertSame(1, $index->graphCount());
    }
    public function testConstantCompilesARequestedInitializerOnce(): void
    {
        $source = '<?php const ANSWER = 40+2;';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $constant = $index->constant('ANSWER');
        self::assertNotNull($constant);
        self::assertSame(['constant', 'constant', 'binary'], array_column($constant->blocks[0]->instructions, 'operation'));
        self::assertSame($constant, $index->constant('ANSWER'));
        self::assertNull($index->constant('answer'));
    }
    public function testCallOwnersDoesNotCompileUnrelatedBodies(): void
    {
        $source = '<?php function used(){sink(1);} function other(){unrelated();}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['used'], $index->callOwners('sink'));
        self::assertSame(0, $index->graphCount());
    }
    public function testPropertyOwnersDoesNotLowerUnrelatedBodies(): void
    {
        $source = '<?php class A{public $x;function set(){ $this->x=1; }} function unrelated(){return 2;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame(['A::set'], $index->propertyOwners('x'));
        self::assertSame(0, $index->graphCount());
    }
    public function testVariantsRetainsBothConditionalDeclarations(): void
    {
        $source = '<?php if($x){function f(){return 1;}}else{function f(){return 2;}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertCount(1, $index->variants('f'));
        self::assertSame([], $index->diagnostics());
    }
    public function testHasSymbolPreservesExactSpellingWithoutCompiling(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php function Target(){}')));
        $index = new Index('test', [$tree]);
        self::assertTrue($index->hasSymbol('Target'));
        self::assertFalse($index->hasSymbol('target'));
        self::assertFalse($index->hasSymbol('missing'));
        self::assertSame(0, $index->graphCount());
    }

    public function testClosureOwnersFindsTheLexicalParentBeforeLowering(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php function owner(){return fn()=>sink();}')));
        $index = new Index('test', [$tree]);
        $owners = $index->callOwners('sink');
        self::assertCount(1, $owners);
        self::assertSame(['owner'], $index->closureOwners($owners[0]));
        self::assertSame(0, $index->graphCount());
    }

    public function testGlobalOwnersExcludesFunctionLocals(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php $x=1; function f(){$local=2;}')));
        $index = new Index('test', [$tree]);
        self::assertSame(['script:fixture.php'], $index->globalOwners('x'));
        self::assertSame([], $index->globalOwners('local'));
        self::assertSame(0, $index->graphCount());
    }

}

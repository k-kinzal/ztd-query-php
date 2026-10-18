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
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants;
use Deriver\Deriver\Lowerer\Declaration\Traits\Members;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
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
use PhpParser\Modifiers;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Lowerer\Declaration\Traits\Members
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Members::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
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
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LexicalConstants::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[Small]
final class MembersTest extends TestCase
{
    public function testCandidatesUsesImmediateTraitNamesForAdaptations(): void
    {
        $source = '<?php trait T{function f(){}}trait U{function f(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $class = new ClassDeclaration('B', traits: ['T', 'U']);
        $methods = (new Members($index))->candidates($class);
        self::assertSame(['t', 'u'], array_keys($methods['f']));
    }
    public function testPrecedenceRemovesOnlyExplicitlyExcludedVariants(): void
    {
        $source = '<?php trait T{function f(){}}trait U{function f(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $members = new Members($index);
        $candidates = $members->candidates(new ClassDeclaration('B', traits: ['T', 'U']));
        $adaptation = new Precedence(new Name('T'), 'f', [new Name('U')]);
        self::assertSame(['t'], array_keys($members->precedence($candidates, [$adaptation])['f']));
        self::assertSame(['t', 'u'], array_keys($candidates['f']));
    }
    public function testAliasesKeepsTheOriginalAlongsideAnExcludedTraitAlias(): void
    {
        $source = '<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U{T::f insteadof U;U::f as protected g;}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $body = $index->callable('B::g');
        self::assertNotNull($body);
        self::assertSame(Visibility::Protected, $body->visibility);
        self::assertSame('B', $body->className);
        self::assertSame(['f', 'g'], array_keys($index->classes()['b']->methods));
    }
    public function testSelectReportsUnresolvedConcreteConflicts(): void
    {
        $source = '<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
        self::assertSame([], $index->classes()['b']->methods);
    }
    public function testCandidatesRetainsSourceIdentityAndSkipsUnavailableTraitBodies(): void
    {
        $source = '<?php trait Alpha {function Run(){}} trait Beta {function Other(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        unset($index->declarations['beta::other']);
        $methods = (new Members($index))->candidates(new ClassDeclaration('Consumer', traits: ['ALPHA', 'Missing', 'Beta']));
        self::assertSame(['run'], array_keys($methods));
        self::assertSame(['alpha'], array_keys($methods['run']));
        self::assertSame($index->declarations['alpha::run'], $methods['run']['alpha']);
    }
    public function testPrecedenceFiltersOnlyTheNamedMethodAndRetainsExplicitAliasSources(): void
    {
        $source = '<?php trait Alpha{function Run(){} function other(){}}trait Beta{function Run(){} function other(){}}trait Third{function Run(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $members = new Members($index);
        $candidates = $members->candidates(new ClassDeclaration('Consumer', traits: ['Alpha', 'Beta', 'Third']));
        $alias = new Alias(new Name('Beta'), 'Run', null, 'alternate');
        $precedence = new Precedence(new Name('Alpha'), 'RUN', [new Name('BETA'), new Name('THIRD')]);
        $selected = $members->precedence($candidates, [$alias, $precedence]);
        self::assertSame(['alpha'], array_keys($selected['run']));
        self::assertSame($candidates['other'], $selected['other']);
        self::assertCount(3, $candidates['run']);
    }
    /**
     * @param int|null $modifier Requested adaptation flags
     */
    #[DataProvider('providerAliasFlags')]
    public function testAliasesClonesSyntaxWhileRetainingProvenanceAndUnchangedFlags(?int $modifier, int $expected): void
    {
        $source1 = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $node = new ClassMethod('Run', ['flags' => Modifiers::PUBLIC | Modifiers::STATIC, 'stmts' => []]);
        $source = new CallableSource('TraitName::Run', $node, 'trait.php', 'TraitName', true);
        $adaptation = new Alias(new Name('TRAITNAME'), 'RUN', $modifier, 'Alternate');
        $selected = (new Members($index))->aliases(['run' => ['traitname' => $source]], ['run' => $source], [$adaptation]);
        self::assertSame(['run', 'alternate'], array_keys($selected));
        self::assertSame($source, $selected['run']);
        self::assertNotSame($source->node, $selected['alternate']->node);
        self::assertInstanceOf(ClassMethod::class, $selected['alternate']->node);
        self::assertSame($expected, $selected['alternate']->node->flags);
        self::assertSame(Modifiers::PUBLIC | Modifiers::STATIC, $node->flags);
        self::assertSame('TraitName::Run', $selected['alternate']->symbol);
        self::assertSame('TraitName', $selected['alternate']->className);
        self::assertSame('trait.php', $selected['alternate']->path);
        self::assertTrue($selected['alternate']->strict);
    }
    /**
     * @return iterable<string,array{int|null,int}>
     */
    public static function providerAliasFlags(): iterable
    {
        yield 'no modifier' => [null, Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'zero modifier' => [0, Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'public' => [Modifiers::PUBLIC, Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'protected' => [Modifiers::PROTECTED, Modifiers::PROTECTED | Modifiers::STATIC];
        yield 'private' => [Modifiers::PRIVATE, Modifiers::PRIVATE | Modifiers::STATIC];
        yield 'final preserving visibility' => [Modifiers::FINAL, Modifiers::PUBLIC | Modifiers::STATIC | Modifiers::FINAL];
        yield 'final protected' => [Modifiers::FINAL | Modifiers::PROTECTED, Modifiers::PROTECTED | Modifiers::STATIC | Modifiers::FINAL];
    }
    public function testAliasesAppliesUnqualifiedVisibilityChangesToTheSelectedImplementation(): void
    {
        $source1 = '<?php trait Alpha{function Run(){}}trait Beta{function Run(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $members = new Members($index);
        $all = $members->candidates(new ClassDeclaration('Consumer', traits: ['Alpha', 'Beta']));
        $source = $all['run']['beta'];
        $selected = $members->aliases($all, ['run' => $source], [new Alias(null, 'RUN', Modifiers::PRIVATE, null)]);
        self::assertSame(['run'], array_keys($selected));
        self::assertSame('Beta::Run', $selected['run']->symbol);
        self::assertInstanceOf(ClassMethod::class, $selected['run']->node);
        self::assertTrue($selected['run']->node->isPrivate());
    }
    public function testAliasesLeavesUnresolvedAdaptationsAndNonMethodSourcesUnselected(): void
    {
        $source1 = '<?php function target(){return 1;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source1)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $source = new CallableSource('function', new Function_('function'), 'fixture.php');
        $adaptations = [new Precedence(new Name('TraitName'), 'run', [new Name('Other')]), new Alias(null, 'missing', null, 'unqualified'), new Alias(new Name('Missing'), 'run', null, 'qualified'), new Alias(new Name('TraitName'), 'run', null, 'nonmethod')];
        self::assertSame([], (new Members($index))->aliases(['run' => ['traitname' => $source]], [], $adaptations));
    }
    public function testSelectPrefersAClassDeclarationAndDeduplicatesDiamondImplementations(): void
    {
        $source = '<?php trait Concrete{function Run(){}}trait AbstractTrait{abstract function Run();}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $concrete = $index->declarations['concrete::run'];
        $abstract = $index->declarations['abstracttrait::run'];
        $class = new ClassDeclaration('Consumer', methods: ['own' => 'Consumer::own']);
        $candidates = ['own' => ['first' => $concrete, 'second' => $abstract], 'run' => ['first' => $concrete, 'second' => $concrete, 'abstract' => $abstract], 'abstract' => ['first' => $abstract]];
        self::assertSame(['run' => $concrete], (new Members($index))->select($class, $candidates));
        self::assertSame([], $index->diagnostics());
    }
    public function testSelectReportsAConflictAtItsTraitSourceAndContinuesOtherMethods(): void
    {
        $source = '<?php trait Alpha{function Run(){}}trait Beta{function Run(){} function Safe(){}}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $class = new ClassDeclaration('Consumer', traits: ['Alpha', 'Beta']);
        $members = new Members($index);
        $selected = $members->select($class, $members->candidates($class));
        self::assertSame(['safe' => $index->declarations['beta::safe']], $selected);
        self::assertCount(1, $index->diagnostics());
        $frontier = $index->diagnostics()[0];
        self::assertSame('INVALID_PROGRAM', $frontier->code);
        self::assertSame('trait-method-conflict:Consumer::run', $frontier->operation);
        self::assertSame('fixture.php', $frontier->at->path);
        self::assertSame($index->declarations['alpha::run']->node->getStartFilePos(), $frontier->at->start);
    }
}

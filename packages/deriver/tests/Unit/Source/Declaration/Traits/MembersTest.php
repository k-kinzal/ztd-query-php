<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration\Traits;

use Deriver\ControlFlow\ClassDeclaration;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\Traits\Members;
use PhpParser\Modifiers;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceFixture;

/**
 * @covers \Deriver\Source\Declaration\Traits\Members
 */
#[CoversClass(Members::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[Small]
final class MembersTest extends TestCase
{
    public function testCandidatesUsesImmediateTraitNamesForAdaptations(): void
    {
        $index = SourceFixture::index('<?php trait T{function f(){}}trait U{function f(){}}');
        $class = new ClassDeclaration('B', traits:['T','U']);
        $methods = (new Members($index))->candidates($class);
        self::assertSame(['t','u'], array_keys($methods['f']));
    }
    public function testPrecedenceRemovesOnlyExplicitlyExcludedVariants(): void
    {
        $index = SourceFixture::index('<?php trait T{function f(){}}trait U{function f(){}}');
        $members = new Members($index);
        $candidates = $members->candidates(new ClassDeclaration('B', traits:['T','U']));
        $adaptation = new Stmt\TraitUseAdaptation\Precedence(new Name('T'), 'f', [new Name('U')]);
        self::assertSame(['t'], array_keys($members->precedence($candidates, [$adaptation])['f']));
        self::assertSame(['t','u'], array_keys($candidates['f']));
    }
    public function testAliasesKeepsTheOriginalAlongsideAnExcludedTraitAlias(): void
    {
        $index = SourceFixture::index('<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U{T::f insteadof U;U::f as protected g;}}');
        $body = $index->callable('B::g');
        self::assertNotNull($body);
        self::assertSame('protected', $body->visibility);
        self::assertSame('B', $body->className);
        self::assertSame(['f','g'], array_keys($index->classes()['b']->methods));
    }
    public function testSelectReportsUnresolvedConcreteConflicts(): void
    {
        $index = SourceFixture::index('<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U;}');
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
        self::assertSame([], $index->classes()['b']->methods);
    }

    public function testCandidatesRetainsSourceIdentityAndSkipsUnavailableTraitBodies(): void
    {
        $index = SourceFixture::index('<?php trait Alpha {function Run(){}} trait Beta {function Other(){}}');
        unset($index->declarations['beta::other']);
        $methods = (new Members($index))->candidates(new ClassDeclaration('Consumer', traits:['ALPHA','Missing','Beta']));
        self::assertSame(['run'], array_keys($methods));
        self::assertSame(['alpha'], array_keys($methods['run']));
        self::assertSame($index->declarations['alpha::run'], $methods['run']['alpha']);
    }

    public function testPrecedenceFiltersOnlyTheNamedMethodAndRetainsExplicitAliasSources(): void
    {
        $index = SourceFixture::index('<?php trait Alpha{function Run(){} function other(){}}trait Beta{function Run(){} function other(){}}trait Third{function Run(){}}');
        $members = new Members($index);
        $candidates = $members->candidates(new ClassDeclaration('Consumer', traits:['Alpha','Beta','Third']));
        $alias = new Stmt\TraitUseAdaptation\Alias(new Name('Beta'), 'Run', null, 'alternate');
        $precedence = new Stmt\TraitUseAdaptation\Precedence(new Name('Alpha'), 'RUN', [new Name('BETA'),new Name('THIRD')]);
        $selected = $members->precedence($candidates, [$alias,$precedence]);
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
        $index = SourceFixture::index();
        $node = new Stmt\ClassMethod('Run', ['flags' => Modifiers::PUBLIC | Modifiers::STATIC,'stmts' => []]);
        $source = new CallableSource('TraitName::Run', $node, 'trait.php', 'TraitName', true, 'original-version');
        $adaptation = new Stmt\TraitUseAdaptation\Alias(new Name('TRAITNAME'), 'RUN', $modifier, 'Alternate');
        $selected = (new Members($index))->aliases(['run' => ['traitname' => $source]], ['run' => $source], [$adaptation]);
        self::assertSame(['run','alternate'], array_keys($selected));
        self::assertSame($source, $selected['run']);
        self::assertNotSame($source->node, $selected['alternate']->node);
        self::assertInstanceOf(Stmt\ClassMethod::class, $selected['alternate']->node);
        self::assertSame($expected, $selected['alternate']->node->flags);
        self::assertSame(Modifiers::PUBLIC | Modifiers::STATIC, $node->flags);
        self::assertSame('TraitName::Run', $selected['alternate']->symbol);
        self::assertSame('TraitName', $selected['alternate']->className);
        self::assertSame('trait.php', $selected['alternate']->path);
        self::assertTrue($selected['alternate']->strict);
        self::assertSame('original-version', $selected['alternate']->cacheSalt);
    }

    /**
     * @return iterable<string,array{int|null,int}>
     */
    public static function providerAliasFlags(): iterable
    {
        yield 'no modifier' => [null,Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'zero modifier' => [0,Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'public' => [Modifiers::PUBLIC,Modifiers::PUBLIC | Modifiers::STATIC];
        yield 'protected' => [Modifiers::PROTECTED,Modifiers::PROTECTED | Modifiers::STATIC];
        yield 'private' => [Modifiers::PRIVATE,Modifiers::PRIVATE | Modifiers::STATIC];
        yield 'final preserving visibility' => [Modifiers::FINAL,Modifiers::PUBLIC | Modifiers::STATIC | Modifiers::FINAL];
        yield 'final protected' => [Modifiers::FINAL | Modifiers::PROTECTED,Modifiers::PROTECTED | Modifiers::STATIC | Modifiers::FINAL];
    }

    public function testAliasesAppliesUnqualifiedVisibilityChangesToTheSelectedImplementation(): void
    {
        $index = SourceFixture::index('<?php trait Alpha{function Run(){}}trait Beta{function Run(){}}');
        $members = new Members($index);
        $all = $members->candidates(new ClassDeclaration('Consumer', traits:['Alpha','Beta']));
        $source = $all['run']['beta'];
        $selected = $members->aliases($all, ['run' => $source], [new Stmt\TraitUseAdaptation\Alias(null, 'RUN', Modifiers::PRIVATE, null)]);
        self::assertSame(['run'], array_keys($selected));
        self::assertSame('Beta::Run', $selected['run']->symbol);
        self::assertInstanceOf(Stmt\ClassMethod::class, $selected['run']->node);
        self::assertTrue($selected['run']->node->isPrivate());
    }

    public function testAliasesLeavesUnresolvedAdaptationsAndNonMethodSourcesUnselected(): void
    {
        $index = SourceFixture::index();
        $source = new CallableSource('function', new Stmt\Function_('function'), 'fixture.php');
        $adaptations = [new Stmt\TraitUseAdaptation\Precedence(new Name('TraitName'), 'run', [new Name('Other')]),new Stmt\TraitUseAdaptation\Alias(null, 'missing', null, 'unqualified'),new Stmt\TraitUseAdaptation\Alias(new Name('Missing'), 'run', null, 'qualified'),new Stmt\TraitUseAdaptation\Alias(new Name('TraitName'), 'run', null, 'nonmethod')];
        self::assertSame([], (new Members($index))->aliases(['run' => ['traitname' => $source]], [], $adaptations));
    }

    public function testSelectPrefersAClassDeclarationAndDeduplicatesDiamondImplementations(): void
    {
        $index = SourceFixture::index('<?php trait Concrete{function Run(){}}trait AbstractTrait{abstract function Run();}');
        $concrete = $index->declarations['concrete::run'];
        $abstract = $index->declarations['abstracttrait::run'];
        $class = new ClassDeclaration('Consumer', methods:['own' => 'Consumer::own']);
        $candidates = ['own' => ['first' => $concrete,'second' => $abstract],'run' => ['first' => $concrete,'second' => $concrete,'abstract' => $abstract],'abstract' => ['first' => $abstract]];
        self::assertSame(['run' => $concrete], (new Members($index))->select($class, $candidates));
        self::assertSame([], $index->diagnostics());
    }

    public function testSelectReportsAConflictAtItsTraitSourceAndContinuesOtherMethods(): void
    {
        $index = SourceFixture::index('<?php trait Alpha{function Run(){}}trait Beta{function Run(){} function Safe(){}}');
        $class = new ClassDeclaration('Consumer', traits:['Alpha','Beta']);
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

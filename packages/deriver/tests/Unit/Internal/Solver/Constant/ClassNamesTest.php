<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Constant;

use Deriver\Internal\Solver\Constant\ClassNames;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ClassNames::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(Term::class)]
#[Small]
final class ClassNamesTest extends TestCase
{
    #[DataProvider('providerRuntimeValues')]
    public function testRuntimeUsesOnlyEstablishedObjectClasses(Term $value, string $kind, string $literal): void
    {
        $result = (new ClassNames(SolverFixture::context()))->runtime($value);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
    }

    /**
     * @return iterable<string,array{Term,string,string}>
     */
    public static function providerRuntimeValues(): iterable
    {
        yield 'object' => [new Term('object', 'one', attributes:['class' => 'Box']),'constant','Box'];
        yield 'enum' => [new Term('enum', 'E::A', attributes:['class' => 'E']),'constant','E'];
        yield 'closure' => [new Term('closure', 'closure:a.php:1'),'constant','Closure'];
        yield 'string' => [Term::constant('Box'),'throwable','TypeError'];
        yield 'integer' => [Term::constant(1),'throwable','TypeError'];
        yield 'float' => [Term::constant(1.5),'throwable','TypeError'];
        yield 'boolean' => [Term::constant(true),'throwable','TypeError'];
        yield 'null' => [Term::constant(null),'throwable','TypeError'];
        yield 'array' => [Term::array([]),'throwable','TypeError'];
        yield 'uninitialized' => [new Term('uninitialized'),'throwable','TypeError'];
        yield 'declared subtype' => [Term::parameter('x', 'Box'),'opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'unknown object' => [new Term('object', 'one'),'opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
    }

    #[DataProvider('providerClassReferences')]
    public function testResolveDistinguishesLexicalNamesFromRuntimeStrings(Term $value, bool $literal, string $kind, string $expected): void
    {
        $context = SolverFixture::context('<?php class Base{}class Box extends Base{function value(){}}class Child extends Box{}function target(){}');
        $caller = $context->program->callable('Box::value');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'Child';
        $resolved = (new ClassNames($context))->resolve($value, $literal, $caller, $state);
        self::assertSame($kind, $resolved->kind);
        self::assertSame($expected, $resolved->literal);
    }

    /**
     * @return iterable<string,array{Term,bool,string,string}>
     */
    public static function providerClassReferences(): iterable
    {
        yield 'literal self' => [Term::constant('self'),true,'constant','Box'];
        yield 'literal parent' => [Term::constant('parent'),true,'constant','Base'];
        yield 'literal static' => [Term::constant('static'),true,'constant','Child'];
        yield 'literal unknown' => [Term::constant('Missing'),true,'constant','Missing'];
        yield 'runtime string' => [Term::constant('bOx'),false,'constant','bOx'];
        yield 'qualified runtime string' => [Term::constant('\\Box'),false,'constant','Box'];
        yield 'runtime self' => [Term::constant('self'),false,'throwable','Error'];
        yield 'runtime parent' => [Term::constant('parent'),false,'throwable','Error'];
        yield 'runtime static' => [Term::constant('static'),false,'throwable','Error'];
        yield 'empty runtime name' => [Term::constant(''),false,'throwable','Error'];
        yield 'object' => [new Term('object', 'one', attributes:['class' => 'Box']),false,'constant','Box'];
        yield 'enum' => [new Term('enum', 'E::A', attributes:['class' => 'E']),false,'constant','E'];
        yield 'closure' => [new Term('closure', 'closure:a.php:1'),false,'constant','Closure'];
        yield 'array' => [Term::array([]),false,'throwable','Error'];
        yield 'integer' => [Term::constant(1),false,'throwable','Error'];
        yield 'uninitialized' => [new Term('uninitialized'),false,'throwable','Error'];
        yield 'symbolic' => [Term::parameter('x'),false,'opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
    }

    public function testResolveRejectsRelativeNamesWithoutARuntimeScope(): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $result = (new ClassNames($context))->resolve(Term::constant('self'), true, $caller, new State());
        self::assertSame('throwable', $result->kind);
        self::assertSame('Error', $result->literal);
    }

    public function testCanonicalUsesCapturedAndSupportedNativeDeclarationSpelling(): void
    {
        $names = new ClassNames(SolverFixture::context('<?php namespace N;class Box{}'));
        self::assertSame('N\\Box', $names->canonical('n\\bOx'));
        self::assertSame('ErrorException', $names->canonical('errorexception'));
        self::assertSame('Closure', $names->canonical('cLoSuRe'));
        self::assertNull($names->canonical('Missing'));
    }

    public function testObjectDoesNotTreatASubtypeBoundAsAnExactRuntimeClass(): void
    {
        $names = new ClassNames(SolverFixture::context());
        self::assertNull($names->object(Term::parameter('x', 'Box')));
        self::assertNull($names->object(Term::constant('Box')));
        self::assertSame('Closure', $names->object(new Term('closure', 'c')));
        self::assertSame('Box', $names->object(new Term('object', 'one', attributes:['class' => 'Box'])));
    }

    public function testRuntimePreservesConfidentialObjectIdentityLabels(): void
    {
        $names = new ClassNames(SolverFixture::context());
        $value = new Term('object', 'one', attributes:['class' => 'Box'], secret:true);
        $result = $names->runtime($value);
        self::assertSame('Box', $result->literal);
        self::assertTrue($result->isSecret());
        $public = $names->runtime(new Term('object', 'two', attributes:['class' => 'Box']));
        self::assertFalse($public->isSecret());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Constant;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ClassNames::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(State::class)]
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
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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

    #[DataProvider('providerReceiverClasses')]
    public function testReceiverPreservesInstanceBoundsAndStaticClassSyntax(Term $value, bool $static, bool $literal, string $kind, string $expected): void
    {
        $context = SolverFixture::context('<?php class Base{}class Box extends Base{function value(){}}class Child extends Box{}function target(){}');
        $caller = $context->program->callable('Box::value');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'Child';
        $instruction = new Instruction('call', 'invoke-static', $caller->source, 'result', attributes: ['literal-class' => $literal]);
        $resolved = (new ClassNames($context))->receiver($caller, $instruction, $state, $value, $static);
        self::assertSame($kind, $resolved->kind);
        self::assertSame($expected, $resolved->literal);
    }

    /**
     * @return iterable<string,array{Term,bool,bool,string,string}>
     */
    public static function providerReceiverClasses(): iterable
    {
        yield 'instance class' => [new Term('object', 'one', attributes:['class' => 'Box']),false,false,'constant','Box'];
        yield 'instance subtype bound' => [Term::parameter('x', 'Box'),false,false,'constant','Box'];
        yield 'instance relative type' => [Term::parameter('x', 'self'),false,false,'constant','Box'];
        yield 'unknown instance' => [new Term('object', 'one'),false,false,'constant',''];
        yield 'static object' => [new Term('object', 'one', attributes:['class' => 'Box']),true,false,'constant','Box'];
        yield 'static runtime string' => [Term::constant('\\bOx'),true,false,'constant','bOx'];
        yield 'static literal self' => [Term::constant('self'),true,true,'constant','Box'];
        yield 'static literal parent' => [Term::constant('parent'),true,true,'constant','Base'];
        yield 'static late bound' => [Term::constant('static'),true,true,'constant','Child'];
        yield 'static dynamic relative' => [Term::constant('self'),true,false,'throwable','Error'];
        yield 'invalid static type' => [Term::constant(1),true,false,'throwable','Error'];
        yield 'unknown static type' => [Term::parameter('x'),true,false,'opaque','UNSUPPORTED_LANGUAGE_FEATURE'];
    }
}

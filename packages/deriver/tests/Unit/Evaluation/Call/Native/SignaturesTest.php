<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
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
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\ConstantSignatures;
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
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Native\Signatures
 */
#[CoversClass(Signatures::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
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
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Term::class)]
#[Small]
final class SignaturesTest extends TestCase
{
    public function testFamilyRecognizesInheritedNativeConstructors(): void
    {
        $signatures = new Signatures(\Tests\Fake\SolverFixture::context('<?php class A extends ErrorException{}')->program);
        self::assertSame('ErrorException', $signatures->family('A'));
        self::assertSame('Exception', $signatures->family('LogicException'));
        self::assertSame('Error', $signatures->family('TypeError'));
        self::assertSame('', $signatures->family('stdClass'));
    }
    public function testParametersIncludesErrorExceptionSpecificSlots(): void
    {
        $signatures = new Signatures(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame(['string|null',null], $signatures->parameters('ErrorException')['filename']);
        self::assertSame(['Throwable|null',null], $signatures->parameters('Exception')['previous']);
    }
    public function testGraphDisallowsSurplusNativeArguments(): void
    {
        $signature = (new Signatures(\Tests\Fake\SolverFixture::context()->program))->graph('Exception', '__construct', new SourceRef('s', 'x.php', 0, 1));
        self::assertCount(3, $signature->parameters);
        self::assertFalse($signature->allowExtraArguments);
        self::assertSame('message', $signature->parameters[0]->name);
        self::assertNotNull($signature->parameters[0]->default);
    }
}

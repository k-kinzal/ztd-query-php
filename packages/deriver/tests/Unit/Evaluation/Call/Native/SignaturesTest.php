<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Reference\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Native\Signatures
 */
#[CoversClass(Signatures::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Builtins::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
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
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Declaration\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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

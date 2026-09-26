<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Native;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Native\Signatures
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SignaturesTest extends TestCase
{
    public function testFamilyRecognizesInheritedNativeConstructors(): void
    {
        $signatures = new \Deriver\Internal\Solver\Call\Native\Signatures(\Tests\Fake\SolverFixture::context('<?php class A extends ErrorException{}')->program);
        self::assertSame('ErrorException', $signatures->family('A'));
        self::assertSame('Exception', $signatures->family('LogicException'));
        self::assertSame('Error', $signatures->family('TypeError'));
        self::assertSame('', $signatures->family('stdClass'));
    }
    public function testParametersIncludesErrorExceptionSpecificSlots(): void
    {
        $signatures = new \Deriver\Internal\Solver\Call\Native\Signatures(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame(['string|null',null], $signatures->parameters('ErrorException')['filename']);
        self::assertSame(['Throwable|null',null], $signatures->parameters('Exception')['previous']);
    }
    public function testGraphDisallowsSurplusNativeArguments(): void
    {
        $signature = (new \Deriver\Internal\Solver\Call\Native\Signatures(\Tests\Fake\SolverFixture::context()->program))->graph('Exception', '__construct', new \Deriver\Api\Reference\SourceRef('s', 'x.php', 0, 1));
        self::assertCount(3, $signature->parameters);
        self::assertFalse($signature->allowExtraArguments);
        self::assertSame('message', $signature->parameters[0]->name);
        self::assertNotNull($signature->parameters[0]->default);
    }
}

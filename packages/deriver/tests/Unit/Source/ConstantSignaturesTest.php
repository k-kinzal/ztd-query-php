<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\ProjectIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\ConstantSignatures
 */
#[CoversClass(ConstantSignatures::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ConstantSignaturesTest extends TestCase
{
    public function testReadKeepsEnumBackingTypesAndCaseIdentity(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $node = new \PhpParser\Node\Stmt\Enum_('Flag', ['scalarType' => new \PhpParser\Node\Identifier('int'),'stmts' => [new \PhpParser\Node\Stmt\EnumCase('A', new \PhpParser\Node\Scalar\Int_(1))]]);
        $index = new ProjectIndex('test', new ProjectInput([]), new TargetProfile());
        $constants = (new ConstantSignatures())->read($index, $node, 'Flag');
        self::assertTrue($constants['A']->enum);
        self::assertSame('int', $constants['A']->type);
    }
    public function testInitializersKeepsBackingExpressionsInTheirDeclaringScope(): void
    {
        $index = new ProjectIndex('test', new ProjectInput([new SourceFile('a.php', '<?php enum Flag:int{case A=1+2;}')]), new TargetProfile());
        self::assertArrayHasKey('flag::A', $index->constantSources);
        self::assertSame('Flag', $index->constantSources['flag::A']->className);
    }
}

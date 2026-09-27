<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Source\ConstantSignatures
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ConstantSignaturesTest extends TestCase
{
    public function testReadKeepsEnumBackingTypesAndCaseIdentity(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $node = new \PhpParser\Node\Stmt\Enum_('Flag', ['scalarType' => new \PhpParser\Node\Identifier('int'),'stmts' => [new \PhpParser\Node\Stmt\EnumCase('A', new \PhpParser\Node\Scalar\Int_(1))]]);
        $index = new \Deriver\Internal\Frontend\Php\ProjectIndex('test', new \Deriver\Api\Project\ProjectInput([]), new \Deriver\Api\Project\TargetProfile());
        $constants = (new \Deriver\Internal\Frontend\Php\Source\ConstantSignatures())->read($index, $node, 'Flag');
        self::assertTrue($constants['A']->enum);
        self::assertSame('int', $constants['A']->type);
    }
    public function testInitializersKeepsBackingExpressionsInTheirDeclaringScope(): void
    {
        $index = new \Deriver\Internal\Frontend\Php\ProjectIndex('test', new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php enum Flag:int{case A=1+2;}')]), new \Deriver\Api\Project\TargetProfile());
        self::assertArrayHasKey('flag::A', $index->constantSources);
        self::assertSame('Flag', $index->constantSources['flag::A']->className);
    }
}

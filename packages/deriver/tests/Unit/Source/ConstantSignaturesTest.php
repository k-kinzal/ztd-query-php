<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
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
 * @covers \Deriver\Source\ConstantSignatures
 */
#[CoversClass(ConstantSignatures::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
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
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
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

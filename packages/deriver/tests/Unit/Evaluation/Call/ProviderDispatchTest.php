<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\Call\ProviderDispatch;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchRequest;
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
 * @covers \Deriver\Evaluation\Call\ProviderDispatch
 */
#[CoversClass(ProviderDispatch::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
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
final class ProviderDispatchTest extends TestCase
{
    public function testResolveKeepsTheWorldOpenWithoutAnApplicableProvider(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $request = new DispatchRequest(Term::parameter('service'), 'run', false, new SourceRef('test', 'fixture.php', 0, 1), $context->configuration->target);
        $result = (new ProviderDispatch($context))->resolve($request);
        self::assertSame([], $result->targets);
        self::assertFalse($result->exhaustive);
    }
}

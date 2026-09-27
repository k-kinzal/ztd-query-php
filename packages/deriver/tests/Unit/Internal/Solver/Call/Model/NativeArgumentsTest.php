<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Model\NativeArguments
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class NativeArgumentsTest extends TestCase
{
    public function testCoerceKeepsSourceAndNullableSignatureRulesSeparate(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $arguments = new \Deriver\Internal\Solver\Call\Model\NativeArguments($context);
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(null, true));
        self::assertSame('', $arguments->coerce(new \Deriver\Internal\IR\Parameter('x', 'string'), $actual, $source)->value->native());
        self::assertTrue($arguments->coerce(new \Deriver\Internal\IR\Parameter('x', 'string'), $actual, $source)->value->isSecret());
        self::assertSame($actual, $arguments->coerce(new \Deriver\Internal\IR\Parameter('x', 'string|null'), $actual, $source));
        self::assertSame($actual, $arguments->coerce(new \Deriver\Internal\IR\Parameter('x', 'array'), $actual, $source));
        self::assertSame('PHP_WARNING', array_values($context->frontiers)[0]->code);
    }
}

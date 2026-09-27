<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Model;

use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Model\NativeArguments
 */
#[CoversClass(NativeArguments::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
final class NativeArgumentsTest extends TestCase
{
    public function testCoerceKeepsSourceAndNullableSignatureRulesSeparate(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $arguments = new NativeArguments($context);
        $actual = new PassedArgument(Term::constant(null, true));
        self::assertSame('', $arguments->coerce(new Parameter('x', 'string'), $actual, $source)->value->native());
        self::assertTrue($arguments->coerce(new Parameter('x', 'string'), $actual, $source)->value->isSecret());
        self::assertSame($actual, $arguments->coerce(new Parameter('x', 'string|null'), $actual, $source));
        self::assertSame($actual, $arguments->coerce(new Parameter('x', 'array'), $actual, $source));
        self::assertSame('PHP_WARNING', array_values($context->frontiers)[0]->code);
    }
}

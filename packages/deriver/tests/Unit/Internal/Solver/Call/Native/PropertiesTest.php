<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Native;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Native\Properties
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PropertiesTest extends TestCase
{
    public function testFindPreservesProtectedAndPrivateNativeVisibility(): void
    {
        $properties = new \Deriver\Internal\Solver\Call\Native\Properties(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame('protected', $properties->find('Exception', 'message')?->visibility);
        self::assertSame('private', $properties->find('Exception', 'previous')?->visibility);
        self::assertSame('int', $properties->find('ErrorException', 'severity')?->type);
        self::assertNull($properties->find('stdClass', 'message'));
    }
    public function testInitializeAllocatesInheritedPreviousAndSeveritySlots(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        (new \Deriver\Internal\Solver\Call\Native\Properties(\Tests\Fake\SolverFixture::context()->program))->initialize('ErrorException', new \Deriver\Value\Term('object', 'e', attributes:['class' => 'ErrorException']), $state);
        self::assertSame(1, $state->memory->read(new \Deriver\Internal\Memory\Location('object:e', ['severity']))->literal);
        self::assertSame('constant', $state->memory->read(new \Deriver\Internal\Memory\Location('object:e', ['Exception::previous']))->kind);
        self::assertNull($state->memory->read(new \Deriver\Internal\Memory\Location('object:e', ['Exception::previous']))->literal);
    }
    public function testGetterUsesTheBaseExceptionPrivateStorage(): void
    {
        $properties = new \Deriver\Internal\Solver\Call\Native\Properties(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame('Exception::previous', $properties->getter('ErrorException', 'getprevious'));
        self::assertSame('Error::previous', $properties->getter('Error', 'getprevious'));
        self::assertNull($properties->getter('Error', 'getseverity'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Memory\Location;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Native\Properties
 */
#[CoversClass(Properties::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Builtins::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Evaluation\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class PropertiesTest extends TestCase
{
    public function testFindPreservesProtectedAndPrivateNativeVisibility(): void
    {
        $properties = new Properties(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame('protected', $properties->find('Exception', 'message')?->visibility);
        self::assertSame('private', $properties->find('Exception', 'previous')?->visibility);
        self::assertSame('int', $properties->find('ErrorException', 'severity')?->type);
        self::assertNull($properties->find('stdClass', 'message'));
    }
    public function testInitializeAllocatesInheritedPreviousAndSeveritySlots(): void
    {
        $state = new State();
        (new Properties(\Tests\Fake\SolverFixture::context()->program))->initialize('ErrorException', new Term('object', 'e', attributes:['class' => 'ErrorException']), $state);
        self::assertSame(1, $state->memory->read(new Location('object:e', ['severity']))->literal);
        self::assertSame('constant', $state->memory->read(new Location('object:e', ['Exception::previous']))->kind);
        self::assertNull($state->memory->read(new Location('object:e', ['Exception::previous']))->literal);
    }
    public function testGetterUsesTheBaseExceptionPrivateStorage(): void
    {
        $properties = new Properties(\Tests\Fake\SolverFixture::context()->program);
        self::assertSame('Exception::previous', $properties->getter('ErrorException', 'getprevious'));
        self::assertSame('Error::previous', $properties->getter('Error', 'getprevious'));
        self::assertNull($properties->getter('Error', 'getseverity'));
    }
}

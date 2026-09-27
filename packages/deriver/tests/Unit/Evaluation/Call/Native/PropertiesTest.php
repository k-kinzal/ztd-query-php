<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Memory\Memory;
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
use Deriver\Value\Arrays;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Native\Properties
 */
#[CoversClass(Properties::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Operations::class)]
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

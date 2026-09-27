<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Dependencies
 */
#[CoversClass(Dependencies::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
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
#[UsesClass(\Deriver\Result\Derivation::class)]
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
#[Small]
final class DependenciesTest extends TestCase
{
    public function testRecordIncludesDataStorageAndControlParents(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $before = new State();
        $before->producers['r0'] = 'value-definition';
        $before->controls = ['branch-definition'];
        $before->addresses['address'] = new Location('cell');
        $before->memory->writers['cell'] = 'previous-write';
        $after = $before->fork();
        $instruction = new Instruction('write', 'write', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['address','r0']);
        (new Dependencies($context))->record($instruction, $before, [$after]);
        self::assertSame(['branch-definition','previous-write','value-definition'], $context->evidence['write']->parents);
        self::assertSame('effect', $context->evidence['write']->kind);
        self::assertSame('write', $after->memory->writers['cell']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\ObservationProvider;
use Deriver\Project\Configuration;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\ObservationProvider
 */
#[CoversClass(ObservationProvider::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ObservationProviderTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testQueriesRetainsTheSessionEntryScope(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        $query = (new \Tests\Fake\MiniContainer())->queries($session->snapshotSession)['entry-return'];
        self::assertSame($session->entrypoints(), $query->scope()->entries);
    }
}

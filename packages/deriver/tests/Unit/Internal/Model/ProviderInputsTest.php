<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\ProviderInputs
 */
#[CoversClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DomainProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Model\Provider\RefinementModel::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ProviderInputsTest extends TestCase
{
    public function testCapturesContributionsBeforeSnapshotConstruction(): void
    {
        $inputs = new \Deriver\Internal\Model\ProviderInputs(new \Deriver\Api\Project\ProjectInput([]), new \Deriver\Api\Project\Configuration(providers:[new \Tests\Fake\MiniContainer(),new \Tests\Fake\PolicyProvider()]));
        self::assertSame('generated/service.php', $inputs->input->files[0]->path);
        self::assertSame('captured', $inputs->configuration->environment['env:APP_NAME']->native());
        self::assertSame('entry', $inputs->entries[0]->symbol);
        self::assertCount(1, $inputs->configuration->domains);
    }
    public function testRejectsEnvironmentConflicts(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Internal\Model\ProviderInputs(new \Deriver\Api\Project\ProjectInput([]), new \Deriver\Api\Project\Configuration(environment:['env:APP_NAME' => \Deriver\Value\Term::constant('conflict')], providers:[new \Tests\Fake\MiniContainer()]));
    }
}

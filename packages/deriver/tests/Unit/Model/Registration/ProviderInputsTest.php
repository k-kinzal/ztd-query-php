<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Registration\ProviderInputs
 */
#[CoversClass(ProviderInputs::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(Term::class)]
#[Small]
final class ProviderInputsTest extends TestCase
{
    public function testCapturesContributionsBeforeSnapshotConstruction(): void
    {
        $inputs = new ProviderInputs(new ProjectInput([]), new Configuration(providers:[new \Tests\Fake\MiniContainer(),new \Tests\Fake\PolicyProvider()]));
        self::assertSame('generated/service.php', $inputs->input->files[0]->path);
        self::assertSame('captured', $inputs->configuration->environment['env:APP_NAME']->native());
        self::assertSame('entry', $inputs->entries[0]->symbol);
        self::assertCount(1, $inputs->configuration->domains);
    }
    public function testRejectsEnvironmentConflicts(): void
    {
        $this->expectException(InvalidInputException::class);
        new ProviderInputs(new ProjectInput([]), new Configuration(environment:['env:APP_NAME' => Term::constant('conflict')], providers:[new \Tests\Fake\MiniContainer()]));
    }
}

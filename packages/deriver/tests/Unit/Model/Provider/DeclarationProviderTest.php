<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DeclarationProvider
 */
#[CoversClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[Small]
final class DeclarationProviderTest extends TestCase
{
    public function testDeclarationsAreDataAvailableToTheSourceIndex(): void
    {
        $input = (new \Tests\Fake\MiniContainer())->declarations();
        self::assertSame('generated/service.php', $input->files[0]->path);
        self::assertStringContainsString('class GeneratedService', $input->files[0]->contents);
    }
}

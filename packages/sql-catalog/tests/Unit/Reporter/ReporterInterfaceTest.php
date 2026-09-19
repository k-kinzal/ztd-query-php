<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\HtmlReporter;
use SqlCatalog\Reporter\JsonReporter;
use SqlCatalog\Reporter\ReporterInterface;
use SqlCatalog\Reporter\TextReporter;

#[CoversClass(ReporterInterface::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(HtmlReporter::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(TextReporter::class)]
final class ReporterInterfaceTest extends TestCase
{
    public function testNameIsUniqueAcrossTheReportersThatShip(): void
    {
        $names = array_map(
            static fn (ReporterInterface $reporter): string => $reporter->name(),
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );
        self::assertSame($names, array_values(array_unique($names)));
    }

    public function testDescriptionIsAlwaysWritten(): void
    {
        $descriptions = array_map(
            static fn (ReporterInterface $reporter): string => $reporter->description(),
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );
        self::assertNotContains('', $descriptions);
    }

    public function testRenderAlwaysProducesSomethingToShow(): void
    {
        $primary = array_map(
            static fn (ReporterInterface $reporter): bool => $reporter->render(new Catalog())->primary() !== null,
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );

        self::assertSame([true, true, true], $primary);
    }
}

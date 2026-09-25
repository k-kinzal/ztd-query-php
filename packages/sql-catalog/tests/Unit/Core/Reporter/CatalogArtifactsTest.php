<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Reporter\CatalogArtifacts;

#[CoversClass(CatalogArtifacts::class)]
final class CatalogArtifactsTest extends TestCase
{
    public function testOneHoldsASingleFile(): void
    {
        self::assertSame(['a.json' => '{}'], CatalogArtifacts::one('a.json', '{}')->all());
    }

    public function testAllReturnsTheFilesInNameOrder(): void
    {
        self::assertSame(['a.txt', 'b.txt'], array_keys((new CatalogArtifacts(['b.txt' => 'b', 'a.txt' => 'a']))->all()));
    }

    public function testNamesListsTheFileNames(): void
    {
        self::assertSame(['a.txt', 'b.txt'], (new CatalogArtifacts(['b.txt' => 'b', 'a.txt' => 'a']))->names());
    }

    public function testSoleAnswersOnlyForExactlyOneFile(): void
    {
        self::assertSame('{}', CatalogArtifacts::one('a.json', '{}')->sole());
        self::assertNull((new CatalogArtifacts(['a' => '1', 'b' => '2']))->sole());
        self::assertNull((new CatalogArtifacts())->sole());
    }

    public function testPrimaryIsTheFileAReaderWantsWhenOnlyOneCanBeShown(): void
    {
        $artifacts = new CatalogArtifacts(['a.json' => '{}', 'a-schema.json' => '{}'], 'a.json');

        self::assertSame('{}', $artifacts->primary());
        self::assertSame(['a-schema.json', 'a.json'], $artifacts->names());
    }

    public function testPrimaryFallsBackToTheOnlyFile(): void
    {
        self::assertSame('{}', CatalogArtifacts::one('a.json', '{}')->primary());
        self::assertNull((new CatalogArtifacts(['a' => '1', 'b' => '2']))->primary());
    }

    public function testPrimaryIsNullWhenTheNamedFileIsNotThere(): void
    {
        self::assertNull((new CatalogArtifacts(['a.json' => '{}'], 'missing.json'))->primary());
    }

    public function testGetReadsOneFileByName(): void
    {
        $artifacts = CatalogArtifacts::one('a.json', '{}');
        self::assertSame('{}', $artifacts->get('a.json'));
        self::assertNull($artifacts->get('missing'));
    }
}

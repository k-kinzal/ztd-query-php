<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\CatalogArtifacts;

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

    public function testGetReadsOneFileByName(): void
    {
        $artifacts = CatalogArtifacts::one('a.json', '{}');
        self::assertSame('{}', $artifacts->get('a.json'));
        self::assertNull($artifacts->get('missing'));
    }
}

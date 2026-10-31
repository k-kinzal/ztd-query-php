<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\IndexListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;

#[CoversClass(IndexListing::class)]
#[Medium]
final class IndexListingTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, IndexListing, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (IndexListing::cases() as $listing) {
                foreach ([' FROM t', ' FROM t WHERE Key_name = \'PRIMARY\''] as $suffix) {
                    $sql = 'SHOW ' . $listing->value . $suffix;
                    yield "$release->value/$sql" => [$release, $listing, $sql];
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsTheChosenListingKeyword(GrammarRelease $release, IndexListing $listing, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $release->value))->analyze($sql);
        self::assertInstanceOf(ShowKeys::class, $statement);
        self::assertSame($listing, $statement->listing);
        self::assertSame($sql, $statement->toString());
    }
}

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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\DatabaseListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;

#[CoversClass(DatabaseListing::class)]
#[Medium]
final class DatabaseListingTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, DatabaseListing, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (DatabaseListing::cases() as $listing) {
                foreach (['', ' LIKE \'p%\'', ' WHERE `Database` = \'pbt\''] as $suffix) {
                    $sql = 'SHOW ' . $listing->value . $suffix;
                    yield "$release->value/$sql" => [$release, $listing, $sql];
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsTheChosenListingKeyword(GrammarRelease $release, DatabaseListing $listing, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $release->value))->analyze($sql);
        self::assertInstanceOf(ShowDatabases::class, $statement);
        self::assertSame($listing, $statement->listing);
        self::assertSame($sql, $statement->toString());
    }
}

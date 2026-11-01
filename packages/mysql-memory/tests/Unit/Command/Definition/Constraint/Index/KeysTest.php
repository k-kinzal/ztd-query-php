<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint\Index;

use MySqlMemory\Command\Definition\Constraint\Index\Keys;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Keys::class)]
#[Medium]
final class KeysTest extends TestCase
{
    public function testBuildPreservesOrderedSlotsPrefixesAndNames(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b VARCHAR(10),KEY idx(a,b(3)),CONSTRAINT u UNIQUE(b))');
        $keys = $session->instance->dictionary->table('d', 't')?->definition->keys ?? [];
        self::assertSame(['idx', 'u'], array_column($keys, 'name'));
        self::assertSame([[0, 1], [1]], array_column($keys, 'columns'));
        self::assertSame([[null, 3], [null]], array_column($keys, 'prefixes'));
    }
    #[DataProvider('providerOrdering')]
    public function testDescendingRetainsOnlyOrderingSupportedByTheRelease(string $version, string $engine, bool $descending): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t(a INT, KEY i(a DESC)) ENGINE=$engine");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([$descending], $table->definition->keys[0]->descending);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerOrdering(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach (['InnoDB', 'MyISAM', 'MEMORY'] as $engine) {
                yield "$version/$engine" => [$version, $engine, false];
            }
        }
        yield '8.4.7/InnoDB' => ['8.4.7', 'InnoDB', true];
    }

    #[DataProvider('providerUnsupportedEngines')]
    public function testDescendingRejectsUnsupportedEngines(string $engine): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1178);
        $this->expectExceptionMessage("The storage engine for the table doesn't support descending indexes");
        $session->query("CREATE TABLE t(a INT, KEY i(a DESC)) ENGINE=$engine");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsupportedEngines(): iterable
    {
        yield 'MyISAM' => ['MyISAM'];
        yield 'MEMORY' => ['MEMORY'];
    }

}

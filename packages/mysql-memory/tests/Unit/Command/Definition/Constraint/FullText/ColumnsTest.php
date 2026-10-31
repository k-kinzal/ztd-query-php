<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint\FullText;

use MySqlMemory\Command\Definition\Constraint\FullText\Columns;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Columns::class)]
#[Small]
final class ColumnsTest extends TestCase
{
    #[DataProvider('providerInvalidColumns')]
    public function testCheckRefusesNoncharacterFullTextColumns(string $type): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $this->expectExceptionCode(1283);
        $session->query('CREATE TABLE t(a ' . $type . ', FULLTEXT(a))');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidColumns(): iterable
    {
        foreach (['INT', 'DECIMAL(5,2)', 'DATE', 'BINARY(10)', 'VARBINARY(10)', 'BLOB', "ENUM('a','b')", "SET('a','b')"] as $type) {
            yield $type => [$type];
        }
    }

    public function testCheckAcceptsCharacterColumnsIncludingBinaryCollations(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a CHAR(10), b VARCHAR(10), c TEXT, FULLTEXT(a,b,c)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
        $table = $session->instance->dictionary->table('d', 't', $session->temporaries);
        self::assertNotNull($table);
        self::assertCount(1, $table->definition->keys);
    }
}

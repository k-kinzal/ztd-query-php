<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\FixedColumns;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;

#[CoversClass(FixedColumns::class)]
#[Small]
final class FixedColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, true>}>
     */
    public static function providerFilters(): iterable
    {
        yield 'unfiltered' => ['', []];
        yield 'like' => ["LIKE 't'", []];
        yield 'equality' => ["WHERE Tables_in_d='t'", ['tables_in_d' => true]];
        yield 'reverse equality' => ["WHERE 't'=Tables_in_d", ['tables_in_d' => true]];
        yield 'singleton list' => ["WHERE Tables_in_d IN ('t')", ['tables_in_d' => true]];
        yield 'multiple choices' => ["WHERE Tables_in_d IN ('t','u')", []];
        yield 'disjunction' => ["WHERE Tables_in_d='t' OR Table_type='VIEW'", []];
    }

    /**
     * @param array<string, true> $expected
     */
    #[DataProvider('providerFilters')]
    public function testOfFindsOnlyConjunctiveFixedNames(string $filter, array $expected): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $operation = $session->analyze('SHOW FULL TABLES FROM d ' . $filter);
        self::assertInstanceOf(ShowTables::class, $operation->statement);

        self::assertSame($expected, FixedColumns::of($operation->statement->filter, $operation->facts));
    }

    public function testColumnDoesNotTreatAComparisonBetweenColumnsAsFixed(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $operation = $session->analyze('SHOW FULL TABLES FROM d WHERE Tables_in_d=Table_type');
        self::assertInstanceOf(ShowTables::class, $operation->statement);

        self::assertSame([], FixedColumns::of($operation->statement->filter, $operation->facts));
    }
}

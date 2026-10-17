<?php

declare(strict_types=1);

namespace Tests\Unit;

use Deriver\Exception\InvalidInputException;
use Deriver\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    public function testParseExtractsCallableSelectors(): void
    {
        $query = Query::parse('App\\Repository::find#parameter(id)');
        self::assertSame('App\\Repository::find', $query->target);
        self::assertNull($query->line);
        self::assertNull($query->column);
        self::assertSame('parameter', $query->selector);
        self::assertSame(['id'], $query->arguments);
    }

    public function testParseExtractsUnquotedPathsAndCoordinatesWithoutReadingFiles(): void
    {
        $query = Query::parse('not present/source #1.php:25:5#argument(0)');
        self::assertSame('not present/source #1.php', $query->target);
        self::assertSame(25, $query->line);
        self::assertSame(5, $query->column);
        self::assertSame('argument', $query->selector);
        self::assertSame([0], $query->arguments);
    }

    public function testParsePreservesSelectorSyntaxWithoutResolvingItsMeaning(): void
    {
        $query = Query::parse('App\\missing#selection(name, 2)');
        self::assertSame('selection', $query->selector);
        self::assertSame(['name', 2], $query->arguments);
    }

    #[DataProvider('providerRoundTrips')]
    public function testToStringRoundTripsTheParsedFields(string $input, string $canonical): void
    {
        $query = Query::parse($input);
        self::assertSame($canonical, $query->toString());
        self::assertEquals($query, Query::parse($query->toString()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRoundTrips(): iterable
    {
        yield 'return' => ['App\\answer#return', 'App\\answer#return'];
        yield 'parameter' => ['App\\Repository::find#parameter(id)', 'App\\Repository::find#parameter(id)'];
        yield 'position' => ['src/Repository.php:25:5#argument(0)', 'src/Repository.php:25:5#argument(0)'];
        yield 'named argument' => ['src/Repository.php:25:5#argument(sql)', 'src/Repository.php:25:5#argument(sql)'];
        yield 'windows path' => ['C:\\source files\\Run.php:2:1#argument(1)', 'C:\\source files\\Run.php:2:1#argument(1)'];
        yield 'leading namespace' => ['\\App\\answer#return', '\\App\\answer#return'];
        yield 'whitespace' => [" \n App\\answer #return() \n", 'App\\answer#return'];
        yield 'argument whitespace' => ['target#selection( name, 2 )', 'target#selection(name,2)'];
        yield 'integer boundary' => ['target#selection(' . PHP_INT_MAX . ')', 'target#selection(' . PHP_INT_MAX . ')'];
    }

    #[DataProvider('providerInvalidSelectors')]
    public function testParseRejectsMalformedSyntax(string $input): void
    {
        $this->expectException(InvalidInputException::class);
        Query::parse($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidSelectors(): iterable
    {
        foreach (['', '#return', ' #return', 'target', 'target#', 'target#return(', 'target#return)', 'target#return()junk', 'target#parameter($id)', 'target#argument(-1)', 'target#argument(1.5)', 'target#argument(01)', 'target#selection(a,)', 'target#selection(a,,b)', 'target#selection(a b)', 'target#selection(nested(0))', 'file.php:0:1#argument(0)', 'file.php:1:0#argument(0)', 'file.php:01:1#argument(0)', '"file.php":1:1#argument(0)', "'file.php':1:1#argument(0)", ':1:1#argument(0)', "file\0.php:1:1#argument(0)", "file\n.php:1:1#argument(0)", 'target#argument(' . PHP_INT_MAX . '0)', 'file.php:' . PHP_INT_MAX . '0:1#argument(0)'] as $index => $input) {
            yield 'invalid ' . $index => [$input];
        }
    }
}

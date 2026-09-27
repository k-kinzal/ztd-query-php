<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Literal\Reader;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

#[CoversClass(Reader::class)]
#[Medium]
final class ReaderTest extends TestCase
{
    #[TestWith([MySql::MySql])]
    #[TestWith([PostgreSql::PostgreSql])]
    #[TestWith([Sqlite::Sqlite])]
    public function testReadIsAnInverseOfScalarComposition(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $builder = $semantics->builder();
        self::assertSame("it's \\a\n", $semantics->decodeLiteral($builder->string("it's \\a\n"))->value());
        self::assertSame((string) PHP_INT_MIN, $semantics->decodeLiteral($builder->integer(PHP_INT_MIN))->value());
        self::assertSame(false, $semantics->decodeLiteral($builder->boolean(false))->value());
        self::assertNull($semantics->decodeLiteral($builder->null())->value());
        self::assertSame('-5', $semantics->decodeLiteral($builder->parenthesized($builder->integer(-5)))->value());
    }
    public function testDecodeRejectsEmptyOrCompoundValues(): void
    {
        $semantics = new Semantics(MySql::MySql);
        $reader = new Reader($semantics->language());
        $this->expectException(DecodingException::class);
        $reader->decode([]);
    }
    #[TestWith(['(1) + (2)'])]
    #[TestWith(["-'3'"])]
    #[TestWith(['CURRENT_TIMESTAMP'])]
    #[TestWith(['1::int'])]
    public function testReadDoesNotEvaluateOrCoerceDefaultExpressions(string $expression): void
    {
        $semantics = new Semantics(PostgreSql::PostgreSql);
        $column = $semantics->analyze('CREATE TABLE t(a INT DEFAULT ' . $expression . ')', [])->resolution?->declarations[0]->columns[0] ?? self::fail('Missing column');
        self::assertNotNull($column->defaultValue);
        $this->expectException(DecodingException::class);
        $semantics->decodeLiteral($column->defaultValue);
    }

    #[TestWith(['((1))', true])]
    #[TestWith(['(1)+(2)', false])]
    #[TestWith(['1', false])]
    public function testParenthesizedRequiresOneEnclosingPair(string $sql, bool $expected): void
    {
        $semantics = new Semantics(PostgreSql::PostgreSql);
        $reader = new Reader($semantics->language());
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame($expected, $reader->parenthesized($tokens));
    }

    /**
     * @return iterable<string, array{Dialect, \SqlSemantics\Core\Mode|null, string}>
     */
    public static function providerStrings(): iterable
    {
        foreach ([MySql::MySql, PostgreSql::PostgreSql, Sqlite::Sqlite] as $dialect) {
            foreach (['', "'\"\\", "a\nb\rc\td", '😀é', '\\%\\_', "'; SELECT 1; --"] as $index => $text) {
                yield $dialect->name . '-' . $index => [$dialect, null, $text];
            }
        }
        foreach (range(0, 127) as $byte) {
            yield 'mysql-byte-' . $byte => [MySql::MySql, null, chr($byte) . "'\\"];
            yield 'mysql-no-escapes-' . $byte => [MySql::MySql, \SqlSemantics\Platform\MySql\Mode::fromString('NO_BACKSLASH_ESCAPES'), chr($byte) . "'\\"];
        }
    }
    #[DataProvider('providerStrings')]
    public function testReadRoundTripsStringValuesThroughDeclaredDefaults(Dialect $dialect, ?\SqlSemantics\Core\Mode $mode, string $text): void
    {
        $semantics = new Semantics($dialect, mode: $mode);
        $literal = $semantics->builder()->string($text);
        $sql = 'CREATE TABLE t(a VARCHAR(255) DEFAULT ' . \SqlSemantics\Statement\Writer::render($literal) . ')';
        $default = $semantics->analyze($sql, [])->resolution?->declarations[0]->columns[0]->defaultValue;
        self::assertNotNull($default);
        self::assertSame($text, $semantics->decodeLiteral($default)->value());
    }
}

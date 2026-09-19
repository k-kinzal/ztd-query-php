<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Sql\PlaceholderScanner;
use SqlCatalog\Sql\SqlLexer;
use SqlCatalog\Sql\SqlToken;
use SqlCatalog\Sql\SqlTokenKind;
use SqlCatalog\Sql\TableReader;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TableReader::class)]
#[UsesClass(PlaceholderScanner::class)]
#[UsesClass(SqlLexer::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class TableReaderTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerRead')]
    public function testRead(string $sql, array $expected): void
    {
        self::assertSame($expected, (new TableReader())->read(TextPattern::fromText($sql)));
    }

    /**
     * @return list<array{string, list<string>}>
     */
    public static function providerRead(): array
    {
        return [
            ['SELECT * FROM users', ['users']],
            ['SELECT * FROM users u JOIN orders o ON o.user_id = u.id', ['users', 'orders']],
            ['INSERT INTO users (id) VALUES (1)', ['users']],
            ['UPDATE users SET a = 1', ['users']],
            ['TRUNCATE TABLE users', ['users']],
            ['SELECT * FROM `app`.`users`', ['app.users']],
            ['SELECT * FROM users, orders', ['users']],
            ['SELECT 1', []],
            ['SELECT * FROM (SELECT 1) x', []],
        ];
    }

    public function testReadStopsAtAGapInsteadOfGuessingATable(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        self::assertSame([], (new TableReader())->read($pattern));
    }

    public function testReadNameStopsAtATrailingSeparator(): void
    {
        $tokens = (new SqlLexer())->tokenize('app. ');
        self::assertSame('app', (new TableReader())->readName($tokens));
    }

    public function testReadNameIsNullWhenNothingNamesATable(): void
    {
        self::assertNull((new TableReader())->readName((new SqlLexer())->tokenize('(')));
    }

    public function testIsNameRejectsKeywordsAndGapMarkers(): void
    {
        $reader = new TableReader();
        self::assertTrue($reader->isName(new SqlToken(SqlTokenKind::Word, 'users', 0, 0)));
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Word, 'SELECT', 0, 0)));
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Word, PlaceholderScanner::HOLE_MARKER, 0, 0)));
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Symbol, '(', 0, 0)));
    }

    public function testUnquoteStripsIdentifierQuoting(): void
    {
        $reader = new TableReader();
        self::assertSame('users', $reader->unquote(new SqlToken(SqlTokenKind::Identifier, '`users`', 0, 0)));
        self::assertSame('users', $reader->unquote(new SqlToken(SqlTokenKind::Word, 'users', 0, 0)));
    }
}

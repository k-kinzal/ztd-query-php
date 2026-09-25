<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\PlaceholderScanner;
use SqlCatalog\Core\Sql\SqlLexer;
use SqlCatalog\Core\Sql\SqlToken;
use SqlCatalog\Core\Sql\SqlTokenKind;
use SqlCatalog\Core\Sql\TableReader;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

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
            ['DROP TABLE IF EXISTS users', ['users']],
            ['CREATE TABLE IF NOT EXISTS users (id INT)', ['users']],
            ['SHOW TABLE STATUS', []],
            ['INSERT INTO users (a) VALUES (1) ON DUPLICATE KEY UPDATE a = VALUES(a)', ['users']],
            ['SELECT COUNT(*) FROM  WHERE a = 1', []],
        ];
    }

    public function testReadStopsAtAGapInsteadOfGuessingATable(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        self::assertSame([], (new TableReader())->read($pattern));
    }

    public function testReadKeepsANameThatIsKnownInPart(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())))
            ->concat(TextPattern::fromText('posts p JOIN `'))
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())))
            ->concat(TextPattern::fromText('users` u'));
        self::assertSame(['{$}posts', '{$}users'], (new TableReader())->read($pattern));
    }

    public function testReadDoesNotGuessAQuotedNameThatIsAllGap(): void
    {
        $pattern = TextPattern::fromText('ALTER TABLE `')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())))
            ->concat(TextPattern::fromText('` DROP INDEX x'));
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
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Identifier, '`' . PlaceholderScanner::HOLE_MARKER . '`', 0, 0)));
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Word, 'WHERE', 0, 0)));
        self::assertFalse($reader->isName(new SqlToken(SqlTokenKind::Symbol, '(', 0, 0)));
    }

    public function testUnquoteStripsIdentifierQuoting(): void
    {
        $reader = new TableReader();
        self::assertSame('users', $reader->unquote(new SqlToken(SqlTokenKind::Identifier, '`users`', 0, 0)));
        self::assertSame('users', $reader->unquote(new SqlToken(SqlTokenKind::Word, 'users', 0, 0)));
    }
}

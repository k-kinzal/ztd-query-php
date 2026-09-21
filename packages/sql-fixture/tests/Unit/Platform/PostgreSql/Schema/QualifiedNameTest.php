<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\QualifiedName as Subject;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class QualifiedNameTest extends TestCase
{
    /**
     * @param array{string, string} $expected
     */
    #[DataProvider('providerTableNames')]
    public function testSplitReadsTheSchemaAndTheTableTheCatalogStores(string $tableName, array $expected): void
    {
        self::assertSame($expected, (new Subject(new PostgreSqlParser()))->split($tableName));
    }

    /**
     * @return list<array{string, array{string, string}}>
     */
    public static function providerTableNames(): array
    {
        return [
            ['users', ['public', 'users']],
            ['public.users', ['public', 'users']],
            ['pg_temp_3.users', ['pg_temp_3', 'users']],
            ['Users', ['public', 'users']],
            ['SCHEMA.Users', ['schema', 'users']],
            ['"Users"', ['public', 'Users']],
            ['"My.Table"', ['public', 'My.Table']],
            ['"a""b"', ['public', 'a"b']],
            ['pg_temp_3."order"', ['pg_temp_3', 'order']],
            ['db.public.users', ['public', 'users']],
            ['order', ['public', 'order']],
            ['users; DROP TABLE other', ['public', 'users; DROP TABLE other']],
            ['', ['public', '']],
        ];
    }

    public function testPartsAnswersNothingForTextThatIsNotOneTableName(): void
    {
        $names = new Subject(new PostgreSqlParser());

        self::assertSame(['public', 'users'], $names->parts('public.users'));
        self::assertNull($names->parts('users; DROP TABLE other'));
        self::assertNull($names->parts('order'));
        self::assertNull($names->parts(''));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\ArtifactDirectory;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\LexicalCatalogShape;
use SqlFaker\Grammar\LexicalCoverageCheck;
use SqlFaker\Grammar\LexicalProfileWriter;
use SqlFaker\Grammar\LexicalWitnessCheck;
use SqlFaker\Grammar\LexicalWitnessShape;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\MySqlProfileBuilder;
use SqlFaker\PostgreSql\PgProfileBuilder;
use SqlFaker\Sqlite\SqliteProfileBuilder;
use SqlFaker\Tooling\LexicalProfileBuilder;

#[CoversClass(LexicalProfileBuilder::class)]
#[UsesClass(ArtifactDirectory::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalProfileWriter::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(MySqlProfileBuilder::class)]
#[UsesClass(PgProfileBuilder::class)]
#[UsesClass(SqliteProfileBuilder::class)]
#[UsesClass(SqlVersion::class)]
#[Medium]
final class LexicalProfileBuilderTest extends TestCase
{
    public function testMysqlDelegatesToTheDialectBuilderItWasGiven(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('mysql source unavailable'));

        $builder = new LexicalProfileBuilder(new MySqlProfileBuilder($source));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysql source unavailable');

        $builder->mysql('mysql-8.4.7', MySqlGrammar::load('mysql-8.4.7'));
    }

    public function testPostgreSqlDelegatesToTheDialectBuilderItWasGiven(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('postgres source unavailable'));

        $builder = new LexicalProfileBuilder(null, new PgProfileBuilder($source));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('postgres source unavailable');

        $builder->postgreSql('pg-17.2');
    }

    public function testSqliteDelegatesToTheDialectBuilderItWasGiven(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('sqlite source unavailable'));

        $builder = new LexicalProfileBuilder(null, null, new SqliteProfileBuilder($source));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sqlite source unavailable');

        $builder->sqlite('sqlite-3.47.2');
    }

    public function testAssertCompatibleAcceptsAProfileThatClassifiesEveryTerminal(): void
    {
        (new LexicalProfileBuilder())->assertCompatible(
            [
                'dialect' => 'mysql',
                'version' => 'mysql-8.4.7',
                'catalog' => [
                    'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                    'terminals' => [
                        'IDENT' => [[
                            'id' => 'ident.bare',
                            'sql' => 'users',
                            'tokens' => ['IDENT'],
                            'units' => ['identifier'],
                        ]],
                    ],
                    'terminal_exclusions' => [],
                    'coverage' => [
                        'units' => ['identifier'],
                        'witnessed' => ['identifier' => 'ident.bare'],
                        'excluded' => [],
                    ],
                ],
            ],
            'mysql',
            'mysql-8.4.7',
            ['IDENT'],
        );

        $this->expectNotToPerformAssertions();
    }

    public function testAssertCompatibleRejectsAProfileThatNamesAnotherRelease(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lexical profile identity: mysql mysql-8.4.7');

        (new LexicalProfileBuilder())->assertCompatible(
            ['dialect' => 'mysql', 'version' => 'mysql-8.0.44'],
            'mysql',
            'mysql-8.4.7',
            [],
        );
    }

    public function testAssertCompatibleRejectsAProfileWithNoCatalog(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lexical profile catalog is missing: mysql mysql-8.4.7');

        (new LexicalProfileBuilder())->assertCompatible(
            ['dialect' => 'mysql', 'version' => 'mysql-8.4.7'],
            'mysql',
            'mysql-8.4.7',
            [],
        );
    }

    public function testPublishVersionReportsWhenAnArtifactCannotBeWritten(): void
    {
        $version = new SqlVersion('mysql', 'mysql-8.4.7', '/dev/null/no-such/ast.php', '/dev/null/no-such/lex.php');

        $this->expectException(RuntimeException::class);

        (new LexicalProfileBuilder())->publishVersion($version, '<?php return [];', ['dialect' => 'mysql']);
    }
}

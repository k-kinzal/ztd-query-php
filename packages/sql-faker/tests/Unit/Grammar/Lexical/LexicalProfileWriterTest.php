<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalProfileWriter;
use SqlFaker\Grammar\Resource\ArtifactDirectory;
use SqlFaker\Grammar\SqlVersion;

#[CoversClass(LexicalProfileWriter::class)]
#[UsesClass(ArtifactDirectory::class)]
#[UsesClass(SqlVersion::class)]
final class LexicalProfileWriterTest extends TestCase
{
    public function testExportedWritesAnArrayAsPhpSource(): void
    {
        self::assertSame(
            "[\n    'a' => 1,\n]",
            (new LexicalProfileWriter())->exported(['a' => 1]),
        );
    }

    public function testExportedWritesAListOnOneLine(): void
    {
        self::assertSame('[1, 2]', (new LexicalProfileWriter())->exported([1, 2]));
    }

    public function testExportedWritesAnEmptyArrayAsAPairOfBrackets(): void
    {
        self::assertSame('[]', (new LexicalProfileWriter())->exported([]));
    }

    public function testExportedEscapesTheControlCharactersVarExportWouldWriteLiterally(): void
    {
        self::assertSame('"a\\x00b"', (new LexicalProfileWriter())->exported("a\x00b"));
    }

    public function testExportedEscapesWhatWouldOtherwiseInterpolate(): void
    {
        self::assertSame('"\\$a\\x01"', (new LexicalProfileWriter())->exported("\$a\x01"));
    }

    public function testExportedLeavesAnOrdinaryStringToVarExport(): void
    {
        self::assertSame("'plain'", (new LexicalProfileWriter())->exported('plain'));
    }

    public function testCompactedTurnsKeyedWitnessesIntoLists(): void
    {
        self::assertSame(
            ['catalog' => ['terminals' => ['IDENT' => [['ident.bare', 'users', ['IDENT'], ['identifier']]]]]],
            (new LexicalProfileWriter())->compacted([
                'catalog' => [
                    'terminals' => [
                        'IDENT' => [[
                            'id' => 'ident.bare',
                            'sql' => 'users',
                            'tokens' => ['IDENT'],
                            'units' => ['identifier'],
                        ]],
                    ],
                ],
            ]),
        );
    }

    public function testCompactedKeepsTheContextOfAWitnessThatCarriesOne(): void
    {
        self::assertSame(
            ['catalog' => ['terminals' => ['IDENT' => [['ident.bare', 'users', ['IDENT'], ['identifier'], 'SELECT %s']]]]],
            (new LexicalProfileWriter())->compacted([
                'catalog' => [
                    'terminals' => [
                        'IDENT' => [[
                            'id' => 'ident.bare',
                            'sql' => 'users',
                            'tokens' => ['IDENT'],
                            'units' => ['identifier'],
                            'context_sql' => 'SELECT %s',
                        ]],
                    ],
                ],
            ]),
        );
    }

    public function testCompactedLeavesAProfileWithNoCatalogAlone(): void
    {
        self::assertSame(['dialect' => 'mysql'], (new LexicalProfileWriter())->compacted(['dialect' => 'mysql']));
    }

    public function testCompactedReportsAWitnessThatIsNotShapedLikeOne(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lexical witness while compacting.');

        (new LexicalProfileWriter())->compacted([
            'catalog' => ['terminals' => ['IDENT' => [['id' => 'ident.bare']]]],
        ]);
    }

    public function testCompactedReportsTerminalWitnessesThatAreNotAList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lexical terminal witnesses while compacting.');

        (new LexicalProfileWriter())->compacted(['catalog' => ['terminals' => ['IDENT' => 'users']]]);
    }

    public function testRenderedWritesAFileThatReturnsTheProfile(): void
    {
        $rendered = (new LexicalProfileWriter())->rendered(['dialect' => 'mysql']);

        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $rendered);
        self::assertStringContainsString("return [\n    'dialect' => 'mysql',\n];", $rendered);
    }

    public function testPublishVersionWritesBothArtifactsWhereTheVersionNamesThem(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-publish-' . getmypid();
        $version = new SqlVersion('mysql', 'mysql-8.4.7', $directory . '/ast.php', $directory . '/lexical.php');

        (new LexicalProfileWriter())->publishVersion($version, '<?php return [];', ['dialect' => 'mysql']);

        self::assertStringEqualsFile($version->astPath, '<?php return [];');
        self::assertStringContainsString("'dialect' => 'mysql'", (string) file_get_contents($version->lexicalPath));

        unlink($version->astPath);
        unlink($version->lexicalPath);
        rmdir($directory);
    }

    public function testPublishVersionReportsAnArtifactItCannotWrite(): void
    {
        $version = new SqlVersion('mysql', 'mysql-8.4.7', '/dev/null/no/ast.php', '/dev/null/no/lexical.php');

        $this->expectException(RuntimeException::class);

        (new LexicalProfileWriter())->publishVersion($version, '<?php return [];', ['dialect' => 'mysql']);
    }
}

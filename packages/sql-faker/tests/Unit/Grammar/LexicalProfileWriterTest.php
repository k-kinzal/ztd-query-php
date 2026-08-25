<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexicalProfileWriter;

#[CoversClass(LexicalProfileWriter::class)]
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
        $profile = (new LexicalProfileWriter())->compacted([
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
        ]);

        self::assertSame(
            [['ident.bare', 'users', ['IDENT'], ['identifier']]],
            $profile['catalog']['terminals']['IDENT'],
        );
    }

    public function testCompactedKeepsTheContextOfAWitnessThatCarriesOne(): void
    {
        $profile = (new LexicalProfileWriter())->compacted([
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
        ]);

        self::assertSame(
            [['ident.bare', 'users', ['IDENT'], ['identifier'], 'SELECT %s']],
            $profile['catalog']['terminals']['IDENT'],
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
}

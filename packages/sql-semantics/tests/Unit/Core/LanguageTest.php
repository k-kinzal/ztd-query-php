<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(Language::class)]
#[UsesClass(Mode::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class LanguageTest extends TestCase
{
    public function testParserIsResolvedForTheDefaultReleaseOfADialect(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        self::assertSame('sqlite-3.47.2', $language->version);
        self::assertSame('sqlite-3.47.2', $language->parser()->version());
        self::assertNull($language->mode);
        self::assertSame(Parameters::Native, $language->parameters);
    }

    public function testKeepsTheReleaseModeAndParameterSyntax(): void
    {
        $mode = Mode::fromString('ANSI_QUOTES');
        $language = new Language(MySqlDialect::MySql, 'mysql-8.0.44', $mode, Parameters::Pdo);
        self::assertSame('mysql-8.0.44', $language->version);
        self::assertSame($mode, $language->mode);
        self::assertSame('IDENT_QUOTED', $language->parser()->tokenize('SELECT "x"')[1]->name);
        self::assertSame('PARAM_MARKER', $language->parser()->tokenize('SELECT :id')[1]->name);
    }

    public function testVocabularyIsTheOneOfTheReader(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        self::assertSame($language->values()->vocabulary, $language->vocabulary());
        self::assertNotNull($language->vocabulary()->recipe('nm', 0));
    }

    public function testValuesAreSharedByTheLanguage(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        self::assertSame($language->values(), $language->values());
        self::assertSame($language->values()->vocabulary, $language->vocabulary());
    }
}

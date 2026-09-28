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
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Equality::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\StatementException::class)]
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
        $language = new Language(MySqlDialect::MySql, 'mysql-8.0.44', $mode, Parameters::Named);
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

    public function testVerifyAcceptsAStatementThatReadsBackAsItself(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        [$command, $comments] = $language->values()->command($language->parser()->parse('/* a */ SELECT x FROM t -- b'));
        $statement = new \SqlSemantics\Statement\Statement($language, $command, $comments);
        $language->verify($statement);
        self::assertSame('/* a */ SELECT x FROM t -- b', $statement->toString());
    }

    public function testVerifyRejectsSqlTheReleaseCannotParse(): void
    {
        $language = new Language(MySqlDialect::MySql);
        $command = $language->values()->command($language->parser()->parse('SELECT x FROM t'))[0];
        $this->expectException(\SqlSemantics\Statement\StatementException::class);
        $this->expectExceptionMessage('The statement is not SQL of mysql-8.4.7');
        new \SqlSemantics\Statement\Statement($language, $command, new \SqlSemantics\Statement\Comments([\SqlSemantics\Statement\Statement::BEFORE => ['--x']]));
    }

    public function testVerifyRejectsACommentThatSwallowsTheStatement(): void
    {
        $language = new Language(SqliteDialect::Sqlite);
        $command = $language->values()->command($language->parser()->parse('SELECT x FROM t'))[0];
        $this->expectException(\SqlSemantics\Statement\StatementException::class);
        new \SqlSemantics\Statement\Statement($language, $command, new \SqlSemantics\Statement\Comments([\SqlSemantics\Statement\Statement::BEFORE => ['/* open']]));
    }

    public function testVerifyRejectsSqlReadBackAsAnotherStatement(): void
    {
        $language = new Language(MySqlDialect::MySql);
        $command = $language->values()->command($language->parser()->parse('SELECT 1'))[0];
        $this->expectException(\SqlSemantics\Statement\StatementException::class);
        $this->expectExceptionMessage('read back as other SQL in mysql-8.4.7');
        new \SqlSemantics\Statement\Statement($language, $command, new \SqlSemantics\Statement\Comments([\SqlSemantics\Statement\Statement::AFTER => ['/*!80000 , 2 */']]));
    }

    public function testSerializationKeepsWhatIdentifiesTheLanguageAndNoParser(): void
    {
        $language = new Language(MySqlDialect::MySql, 'mysql-8.0.44', Mode::fromString('ANSI_QUOTES'), Parameters::Named);
        $serialized = serialize($language);
        self::assertStringNotContainsString('SqlParser\\Parser', $serialized);
        self::assertStringNotContainsString('WeakMap', $serialized);
        $copy = unserialize($serialized);
        self::assertInstanceOf(Language::class, $copy);
        self::assertSame(MySqlDialect::MySql, $copy->dialect);
        self::assertSame('mysql-8.0.44', $copy->version);
        self::assertSame(Parameters::Named, $copy->parameters);
        self::assertSame('IDENT_QUOTED', $copy->parser()->tokenize('SELECT "x"')[1]->name);
        $command = $copy->values()->command($copy->parser()->parse('SELECT "x"'))[0];
        self::assertSame('SELECT "x"', (new \SqlSemantics\Statement\Statement($copy, $command))->toString());
    }
}

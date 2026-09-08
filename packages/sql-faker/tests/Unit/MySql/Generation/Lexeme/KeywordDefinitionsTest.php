<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\KeywordDefinitions;
use SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator;

#[CoversClass(KeywordDefinitions::class)]
#[UsesClass(KeywordLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CommonKeywordDefinitions::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
final class KeywordDefinitionsTest extends TestCase
{
    public function testCreateRespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['SELECT_SYM' => ['SELECT']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['SELECT_SYM']), 0, new ResolvedOutput());
        $result = $definitions->create('mysql-8.4.7', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('SELECT', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->create('mysql-9.2.0', $keywords)->generate($input));
    }

    public function testLegacyRespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['SQL_CACHE_SYM' => ['SQL_CACHE']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['SQL_CACHE_SYM']), 0, new ResolvedOutput());
        $result = $definitions->legacy('mysql-5.7.44', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('SQL_CACHE', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->legacy('mysql-8.0.44', $keywords)->generate($input));
    }

    public function testBeforeLtsRespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['MASTER_HOST_SYM' => ['MASTER_HOST']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['MASTER_HOST_SYM']), 0, new ResolvedOutput());
        $result = $definitions->beforeLts('mysql-8.3.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('MASTER_HOST', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->beforeLts('mysql-8.4.7', $keywords)->generate($input));
    }

    public function testMysql56RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['OLD_PASSWORD' => ['OLD_PASSWORD']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['OLD_PASSWORD']), 0, new ResolvedOutput());
        $result = $definitions->mysql56('mysql-5.6.51', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('OLD_PASSWORD', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->mysql56('mysql-5.7.44', $keywords)->generate($input));
    }

    public function testFrom57RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['ACCOUNT_SYM' => ['ACCOUNT']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['ACCOUNT_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from57('mysql-5.7.44', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('ACCOUNT', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from57('mysql-5.6.51', $keywords)->generate($input));
    }

    public function testBeforeLts57RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['MASTER_TLS_VERSION_SYM' => ['MASTER_TLS_VERSION']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['MASTER_TLS_VERSION_SYM']), 0, new ResolvedOutput());
        $result = $definitions->beforeLts57('mysql-8.3.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('MASTER_TLS_VERSION', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->beforeLts57('mysql-8.4.7', $keywords)->generate($input));
    }

    public function testMysql57RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['PARSE_GCOL_EXPR_SYM' => ['PARSE_GCOL_EXPR']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['PARSE_GCOL_EXPR_SYM']), 0, new ResolvedOutput());
        $result = $definitions->mysql57('mysql-5.7.44', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('PARSE_GCOL_EXPR', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->mysql57('mysql-8.0.44', $keywords)->generate($input));
    }

    public function testFrom80RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['WINDOW_SYM' => ['WINDOW']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['WINDOW_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from80('mysql-8.0.44', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('WINDOW', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from80('mysql-5.7.44', $keywords)->generate($input));
    }

    public function testBeforeLts80RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['MASTER_PUBLIC_KEY_PATH_SYM' => ['MASTER_PUBLIC_KEY_PATH']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['MASTER_PUBLIC_KEY_PATH_SYM']), 0, new ResolvedOutput());
        $result = $definitions->beforeLts80('mysql-8.3.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('MASTER_PUBLIC_KEY_PATH', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->beforeLts80('mysql-8.4.7', $keywords)->generate($input));
    }

    public function testFrom81RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['PARSE_TREE_SYM' => ['PARSE_TREE']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['PARSE_TREE_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from81('mysql-8.1.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('PARSE_TREE', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from81('mysql-8.0.44', $keywords)->generate($input));
    }

    public function testFrom82RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['GTIDS_SYM' => ['GTIDS']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['GTIDS_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from82('mysql-8.2.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('GTIDS', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from82('mysql-8.1.0', $keywords)->generate($input));
    }

    public function testFrom83RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['QUALIFY_SYM' => ['QUALIFY']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['QUALIFY_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from83('mysql-8.3.0', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('QUALIFY', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from83('mysql-8.2.0', $keywords)->generate($input));
    }

    public function testFrom84RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['TABLESAMPLE_SYM' => ['TABLESAMPLE']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['TABLESAMPLE_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from84('mysql-8.4.7', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('TABLESAMPLE', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from84('mysql-8.3.0', $keywords)->generate($input));
    }

    public function testFrom90RespectsTheReviewedVersionBoundary(): void
    {
        $definitions = new KeywordDefinitions();
        $keywords = new KeywordLexemeGenerator(['VECTOR_SYM' => ['VECTOR']], []);
        $input = new LexemeInput(TerminalSequence::fromNames(['VECTOR_SYM']), 0, new ResolvedOutput());
        $result = $definitions->from90('mysql-9.0.1', $keywords)->generate($input);
        self::assertNotNull($result);
        self::assertSame('VECTOR', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($definitions->from90('mysql-8.4.7', $keywords)->generate($input));
    }
}

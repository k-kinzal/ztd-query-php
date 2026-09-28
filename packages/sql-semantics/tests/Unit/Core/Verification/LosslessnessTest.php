<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Verification\Losslessness;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

#[CoversClass(Losslessness::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class LosslessnessTest extends TestCase
{
    #[TestWith(['select  a as b from t', 'SELECT a AS b FROM t'])]
    #[TestWith(["select a -- note\nfrom t", "SELECT a -- note\nFROM t"])]
    #[TestWith(['/* lead */ SELECT 1 /* end */', '/* lead */ SELECT 1 /* end */'])]
    public function testOnlyWhitespaceAndKeywordCaseMayDiffer(string $sql, string $written): void
    {
        self::assertNull((new Losslessness(new Language(Sqlite::Sqlite)))->difference($sql, $written));
    }

    #[TestWith(['SELECT a FROM t', 'SELECT A FROM t', 'spelling'])]
    #[TestWith(['SELECT a AS b FROM t', 'SELECT a b FROM t', 'rule'])]
    #[TestWith(['SELECT (a) FROM t', 'SELECT a FROM t', 'rule'])]
    #[TestWith(['SELECT /* x */ a FROM t', 'SELECT a FROM t', 'comments'])]
    #[TestWith(['/* lead */ SELECT a FROM t', 'SELECT a FROM t', 'comments around'])]
    #[TestWith(['SELECT 1e1', 'SELECT 10', 'rule'])]
    #[TestWith(["SELECT 'a'", 'SELECT "a"', 'rule'])]
    #[TestWith(['SELECT x FROM "T"', 'SELECT x FROM "t"', 'spelling'])]
    #[TestWith(['SELECT id FROM t', 'SELECT ID FROM t', 'spelling'])]
    public function testAnythingElseIsADifference(string $sql, string $written, string $kind): void
    {
        $difference = (new Losslessness(new Language(Sqlite::Sqlite)))->difference($sql, $written);
        self::assertNotNull($difference);
        self::assertStringContainsStringIgnoringCase($kind, $difference);
    }

    public function testDifferencesAreFoundInEveryDialect(): void
    {
        self::assertNotNull((new Losslessness(new Language(MySql::MySql)))->difference('SELECT `a`', 'SELECT a'));
        self::assertNull((new Losslessness(new Language(MySql::MySql)))->difference('select /*+ BKA(t) */ a from t', 'SELECT /*+ BKA(t) */ a FROM t'));
        self::assertNotNull((new Losslessness(new Language(PostgreSql::PostgreSql)))->difference('SELECT a <> b', 'SELECT a != b'));
        self::assertNull((new Losslessness(new Language(PostgreSql::PostgreSql)))->difference('select a::text', 'SELECT a :: text'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Verification\Readback;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149 as Commit;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWith_6ac05548 as NoTransaction;
use SqlSemantics\Statement\StatementException;

#[CoversClass(Readback::class)]
#[UsesClass(Language::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(StatementException::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Equality::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[Medium]
final class ReadbackTest extends TestCase
{
    public function testCheckAcceptsAStatementWhoseSqlReadsBackAsItself(): void
    {
        $semantics = new Semantics(MySql::MySql);
        $statement = $semantics->analyze('SELECT /*+ BKA(t) */ a FROM t -- done');
        (new Readback($semantics->language()))->check($statement);
        self::assertSame('SELECT /*+ BKA(t) */ a FROM t -- done', $statement->toString());
    }

    public function testCheckRejectsSqlAnotherReleaseCannotParse(): void
    {
        $statement = (new Semantics(MySql::MySql, 'mysql-8.4.7'))->analyze('WITH x AS (SELECT 1) SELECT * FROM x');
        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('The statement is not SQL of mysql-5.6.51');
        (new Readback(new Language(MySql::MySql, 'mysql-5.6.51')))->check($statement);
    }

    public function testEnclosesLooksThroughFormsThatWriteNothingMore(): void
    {
        $language = new Language(Sqlite::Sqlite);
        $read = $language->values()->command($language->parser()->parse('COMMIT'))[0];
        $readback = new Readback($language);
        self::assertTrue($readback->encloses($read, new Commit('COMMIT', new NoTransaction())));
        self::assertTrue($readback->encloses($read, $read));
        self::assertFalse($readback->encloses($read, new Commit('END', new NoTransaction())));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testPublishAttachesLocalMeaningWithoutChangingTheSuppliedStructure(): void
    {
        $number = new NumberLiteral('12');
        $statement = new QueryRoot(new Select([], [new SelectExpression($number, new Name('value'))]));
        $published = (new Publication())->publish((new Semantics(Dialect::MySql))->context(), $statement);
        self::assertInstanceOf(QueryRoot::class, $published);
        self::assertInstanceOf(Select::class, $published->query);
        self::assertInstanceOf(SelectExpression::class, $published->query->items[0]);
        self::assertFalse($number->hasBinding());
        self::assertFalse($statement->hasStatementInformation());
        self::assertTrue($published->query->items[0]->expression->hasBinding());
        self::assertSame($published->query->queryInformation(), $published->output());
        self::assertSame('value', $published->statementInformation()->field(0)->name?->value);
        self::assertSame('SELECT 12 AS `value`', $published->toString());
    }

    public function testRebindingKeepsInputNoticesAndRecomputesDerivedWarningsOnce(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $original = $semantics->analyze('SELECT /*+ FOO */ SQL_NO_CACHE 1 AS full');
        $bound = (new Publication())->publish($semantics->context(), $original);
        $rebound = (new Publication())->publish($semantics->context(), $bound);
        $messages = static fn (\SqlSemantics\Statement\AnalyzedStatement $statement): array => array_map(static fn ($warning): string => $warning->message(), $statement->warnings());
        self::assertCount(3, $original->warnings());
        self::assertSame($messages($original), $messages($bound));
        self::assertSame($messages($original), $messages($rebound));
        self::assertSame([false, true, true], array_map(static fn ($notice): bool => $notice->fromInput, $rebound->notices()));
        self::assertSame($rebound, $rebound->notices()[1]->subject);
        self::assertNotSame($original, $rebound->notices()[1]->subject);
        self::assertSame($original->notices()[1]->warning, $rebound->notices()[1]->warning);
        self::assertSame($original->notices()[0]->inputSource?->offset, $rebound->notices()[0]->inputSource?->offset);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOptions(): iterable
    {
        $common = [
            'CREATE TABLE t (a INT) ENGINE = InnoDB, MAX_ROWS 3 MIN_ROWS = 1',
            "CREATE TABLE t (a INT) COMMENT = 'a' DATA DIRECTORY = 'b' INDEX DIRECTORY 'c'",
            'CREATE TABLE t (a INT) ROW_FORMAT = DYNAMIC INSERT_METHOD = FIRST UNION = (a, b)',
            'CREATE TABLE t (a INT) DEFAULT CHARSET = latin1 COLLATE = latin1_bin',
            'CREATE TABLE t (a INT) CHAR SET latin1 DEFAULT COLLATE latin1_bin',
            'CREATE TABLE t (a INT) CHARACTER SET = latin1 KEY_BLOCK_SIZE = 8',
            'CREATE TABLE t (a INT, KEY k (a) KEY_BLOCK_SIZE = 8)',
            'ALTER TABLE t ALGORITHM COPY, LOCK = SHARED',
            'CREATE INDEX k ON t (a) ALGORITHM = DEFAULT LOCK NONE',
            'CREATE TABLE t (a INT) PARTITION BY HASH (a) (PARTITION p STORAGE ENGINE = InnoDB MAX_ROWS 10)',
            'CREATE DATABASE d DEFAULT CHARSET = latin1 COLLATE = latin1_bin',
            'ALTER DATABASE d CHAR SET = latin1 DEFAULT COLLATE latin1_bin',
            "CREATE TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE = 1M, ENGINE = InnoDB WAIT",
            "CREATE LOGFILE GROUP lg ADD UNDOFILE 'f' INITIAL_SIZE = 1M, UNDO_BUFFER_SIZE 1M ENGINE = ndb",
            'ALTER TABLESPACE ts ADD DATAFILE \'f\' INITIAL_SIZE = 1M, ENGINE ndb',
            "ALTER LOGFILE GROUP lg ADD UNDOFILE 'f' INITIAL_SIZE 1M, ENGINE = ndb WAIT",
            'DROP TABLESPACE ts ENGINE = ndb, WAIT',
            'DROP LOGFILE GROUP lg STORAGE ENGINE = ndb, WAIT',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $version) {
            foreach ($common as $sql) {
                yield $version . '/' . $sql => [$version, $sql];
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $version) {
            foreach ([
                'CREATE RESOURCE GROUP rg TYPE USER VCPU 0001 2-3, 4 THREAD_PRIORITY 00 DISABLE',
                'ALTER RESOURCE GROUP rg VCPU 1 1',
                'ALTER RESOURCE GROUP rg VCPU = 0, 01 THREAD_PRIORITY = -02 ENABLE FORCE',
                'SET RESOURCE GROUP rg FOR 1 02, 3',
                "CREATE DATABASE d DEFAULT ENCRYPTION = 'N'",
                "CREATE DATABASE d ENCRYPTION 'Y'",
                'ALTER DATABASE d READ ONLY = 1',
                "CLONE LOCAL DATA DIRECTORY = '/tmp/clone'",
                "CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' DATA DIRECTORY = '/tmp/clone' REQUIRE NO SSL",
                "CREATE TABLE t (a INT ENGINE_ATTRIBUTE = '{}', KEY k (a) SECONDARY_ENGINE_ATTRIBUTE = '{}') ENGINE_ATTRIBUTE = '{}'",
                'CREATE TABLE t (a INT) SECONDARY_ENGINE = NULL AUTOEXTEND_SIZE = 4M TABLESPACE = ts',
                "ALTER TABLESPACE ts AUTOEXTEND_SIZE = 4M, ENCRYPTION = 'N' ENGINE_ATTRIBUTE = '{}'",
                "CREATE UNDO TABLESPACE ts ADD DATAFILE 'f' ENGINE = InnoDB",
                'ALTER UNDO TABLESPACE ts SET ACTIVE ENGINE = InnoDB',
                'DROP UNDO TABLESPACE ts ENGINE = InnoDB',
            ] as $sql) {
                yield $version . '/' . $sql => [$version, $sql];
            }
        }
    }

    #[DataProvider('providerOptions')]
    public function testPublishPreservesEveryOptionAndListBoundary(string $version, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $version);
        $statement = (new Publication())->publish($semantics->context(), $semantics->analyze($sql));
        $formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));

        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
    public function testPublishFinishesPreparedParameterTypesBeforeExposingFields(): void
    {
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $placeholder = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(16383, $settings->connection);
        $semantics = new Semantics(Dialect::MySql, '8.4.7', session: $settings->withParameters([$placeholder, $placeholder, $placeholder], [0, 1, 2]));
        $query = $semantics->analyze('SELECT ? / 2 AS direct_value, (? / ?) + 1 AS nested_value');
        self::assertInstanceOf(QueryRoot::class, $query);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $query->field(0)->type);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $query->field(1)->type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $query->field(0)->type->descriptor);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $query->field(1)->type->descriptor);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal, $query->field(0)->type->descriptor->kind);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, $query->field(1)->type->descriptor->kind);
        self::assertSame(4, $query->field(1)->type->descriptor->displayDecimals);
    }

}

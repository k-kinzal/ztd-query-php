<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\Copy;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\CopyCommands;

#[CoversClass(CopyCommands::class)]
#[Medium]
final class CopyCommandsTest extends TestCase
{
    #[TestWith(["COPY t FROM STDIN (ON_ERROR ignore, LOG_VERBOSITY verbose, DEFAULT 'd', FREEZE)", 'COPY "public"."t" FROM STDIN WITH (FREEZE, DEFAULT \'d\', ON_ERROR \'ignore\', LOG_VERBOSITY \'verbose\')'])]
    #[TestWith(["COPY t TO '/x' (FORMAT csv, FORCE_QUOTE (a), ESCAPE '!', QUOTE '$', ENCODING 'UTF8')", 'COPY "public"."t" TO \'/x\' WITH (FORMAT \'csv\', QUOTE \'$\', ESCAPE \'!\', FORCE_QUOTE("a"), ENCODING \'UTF8\')'])]
    #[TestWith(['COPY (TABLE t) TO STDOUT', 'COPY(TABLE "public"."t") TO STDOUT'])]
    public function testWriteUsesTheCanonicalSpelling(string $sql, string $expected): void
    {
        self::assertSame($expected, CopyCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql))?->toString());
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(CopyCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL')));
    }

    public function testTableWritesTheColumnList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t (a) TO STDOUT');
        self::assertInstanceOf(Copy\CopyToStatement::class, $statement);
        self::assertSame('COPY "public"."t"("a")', (new Tree('t', CopyCommands::table($statement->table, $statement->columns)))->toString());
    }

    public function testEndpointWritesTheClientKeywordForTheDirection(): void
    {
        self::assertSame('STDIN', CopyCommands::endpoint(new Copy\Endpoint\CopyClient(), 'STDIN')->toString());
    }

    public function testOptionsOmitDefaults(): void
    {
        self::assertSame([], CopyCommands::options(new Copy\CopyOptions()));
    }

    public function testTextOmitsAMissingValue(): void
    {
        self::assertSame([], CopyCommands::text('NULL', null));
        self::assertSame("NULL ''", CopyCommands::text('NULL', '')[0]->toString());
    }

    public function testChoiceWritesAnAsteriskOrNames(): void
    {
        self::assertSame('FORCE_NULL *', CopyCommands::choice('FORCE_NULL', new Copy\EveryColumn())[0]->toString());
        self::assertSame([], CopyCommands::choice('FORCE_NULL', null));
    }

    public function testOptionWritesTheNameAndArgument(): void
    {
        self::assertSame('HEADER MATCH', CopyCommands::option('HEADER', \SqlSemantics\Model\Sql\Build::keyword('MATCH'))->toString());
    }

    public function testNamesQuotesTheColumns(): void
    {
        self::assertSame('("a", "B")', CopyCommands::names(['a', 'B'])->toString());
    }
}

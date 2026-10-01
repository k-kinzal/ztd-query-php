<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\DeclaredTypeReader;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(DeclaredTypeReader::class)]
#[Medium]
final class DeclaredTypeReaderTest extends TestCase
{
    #[TestWith(['INTEGER', 'INTEGER', Affinity::Integer])]
    #[TestWith(['VARCHAR(-2.5, 0xFF)', 'VARCHAR(-2.5, 0xFF)', Affinity::Text])]
    #[TestWith(['integer', 'INTEGER', Affinity::Integer])]
    #[TestWith(['INTEGER GENERATED ALWAYS', 'INTEGER', Affinity::Integer])]
    #[TestWith(['LONG_CUSTOM ALWAYS', 'LONG_CUSTOM', Affinity::Numeric])]
    #[TestWith(['FLOATING POINT', 'FLOATING POINT', Affinity::Integer])]
    #[TestWith(['A /*INT*/ B', 'A /*INT*/ B', Affinity::Integer])]
    #[TestWith(["'TEXT' 'INT'", 'TEXT', Affinity::Text])]
    #[TestWith(['"INTEGER"(123)', 'INTEGER', Affinity::Integer])]
    #[TestWith(['[BLOB] B', 'BLOB] ', Affinity::Blob])]
    #[TestWith(['`REAL` A', 'REAL', Affinity::Real])]
    #[TestWith(['""', '', Affinity::Blob])]
    #[TestWith(['', null, Affinity::Blob])]
    #[TestWith(['"a""b"', 'a"b', Affinity::Numeric])]
    #[TestWith(['NUMERIC(99999999999999999999999999999999)', 'NUMERIC(99999999999999999999999999999999)', Affinity::Numeric])]
    public function testReadPreservesTheDatabaseTypeNameAndItsStorageBehavior(string $source, ?string $name, Affinity $affinity): void
    {
        $parser = new SqliteParser();
        $reader = new DeclaredTypeReader();
        $sql = 'CREATE TABLE bar (foo ' . $source . ')';
        $type = $reader->read($parser->parse($sql)->find('typetoken')[0]);
        self::assertSame($name, $type->name);
        self::assertSame($affinity, $type->descriptor->affinity);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($type));
        $rebuilt = 'CREATE TABLE bar (foo ' . $type->toString() . ')';
        self::assertEquals($type, $reader->read($parser->parse($rebuilt)->find('typetoken')[0]));
        $original = new PDO('sqlite::memory:');
        $reconstructed = new PDO('sqlite::memory:');
        $original->exec($sql);
        $reconstructed->exec($rebuilt);
        $original->exec("INSERT INTO bar VALUES ('12'), ('3.25'), (12), (X'01'), (NULL)");
        $reconstructed->exec("INSERT INTO bar VALUES ('12'), ('3.25'), (12), (X'01'), (NULL)");
        $originalSchema = $original->query('PRAGMA table_info(bar)');
        $rebuiltSchema = $reconstructed->query('PRAGMA table_info(bar)');
        self::assertInstanceOf(PDOStatement::class, $originalSchema);
        self::assertInstanceOf(PDOStatement::class, $rebuiltSchema);
        self::assertSame($originalSchema->fetchAll(PDO::FETCH_ASSOC), $rebuiltSchema->fetchAll(PDO::FETCH_ASSOC));
        $originalRows = $original->query('SELECT foo, typeof(foo) FROM bar');
        $rebuiltRows = $reconstructed->query('SELECT foo, typeof(foo) FROM bar');
        self::assertInstanceOf(PDOStatement::class, $originalRows);
        self::assertInstanceOf(PDOStatement::class, $rebuiltRows);
        self::assertSame($originalRows->fetchAll(PDO::FETCH_ASSOC), $rebuiltRows->fetchAll(PDO::FETCH_ASSOC));
    }

    #[TestWith(['INTEGER', 1])]
    #[TestWith(['"INTEGER"(123)', null])]
    #[TestWith(['INTEGER(123)', null])]
    #[TestWith(['INT', null])]
    public function testReadIdentifiesActualRowidAliasEligibility(string $source, ?int $generated): void
    {
        $sql = 'CREATE TABLE bar (foo ' . $source . ' PRIMARY KEY)';
        $type = (new DeclaredTypeReader())->read((new SqliteParser())->parse($sql)->find('typetoken')[0]);
        self::assertSame($generated !== null, $type->permitsRowidAlias());
        $database = new PDO('sqlite::memory:');
        $database->exec($sql);
        $database->exec('INSERT INTO bar DEFAULT VALUES');
        $result = $database->query('SELECT foo FROM bar');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($generated, $result->fetchColumn());
    }
}

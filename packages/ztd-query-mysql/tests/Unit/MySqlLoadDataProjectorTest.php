<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlLoadDataProjector;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;

#[CoversClass(MySqlLoadDataProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlLoadDataProjectorTest extends TestCase
{
    public function testProjectsDefaultTabSeparatedInputAndNullMarker(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(20, fwrite($stream, "1\tAlice\n2\t\\N\n3\tA\\tB\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(50), slug VARCHAR(50) AS (LOWER(name)))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA LOCAL INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items IGNORE 0 LINES";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringStartsWith('INSERT IGNORE INTO `items` (`id`, `name`) VALUES ', $projected);
        self::assertStringContainsString("CAST('Alice' AS CHAR)", $projected);
        self::assertStringContainsString('NULL', $projected);
        self::assertStringContainsString("CAST('A", $projected);
        self::assertStringContainsString("B' AS CHAR)", $projected);
        self::assertStringNotContainsString('`slug`', $projected);
        self::assertSame(2, substr_count($projected, '), ('));
    }

    public function testProjectsCsvPrefixesIgnoredRowsUserVariablesAndSetExpressions(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "header\r\n>1,\"Alice, A\"\r\nignored\r\n>2,\"NULL\"\r\n";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(50), source VARCHAR(50) DEFAULT \'load\')',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\'"
            . " LINES STARTING BY '>' TERMINATED BY '\\r\\n' IGNORE 1 LINES (id, @raw)"
            . ' SET name = UPPER(@raw)';
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringStartsWith('INSERT INTO `items` (`id`, `name`) VALUES ', $projected);
        self::assertStringContainsString("UPPER(CAST('Alice, A' AS CHAR))", $projected);
        self::assertStringContainsString("UPPER(CAST('NULL' AS CHAR))", $projected);
        self::assertStringContainsString("(CAST('1' AS CHAR)", $projected);
        self::assertStringNotContainsString("CAST('>1' AS CHAR)", $projected);
        self::assertStringNotContainsString('header', $projected);
        self::assertStringNotContainsString('ignored', $projected);
        self::assertSame(1, substr_count($projected, '), ('));
    }

    public function testProjectsReplaceAndMissingFieldsAsDefault(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(2, fwrite($stream, "7\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(50) DEFAULT \'unknown\')',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' REPLACE INTO TABLE items (id, name)";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringStartsWith('REPLACE INTO `items` (`id`, `name`) VALUES ', $projected);
        self::assertStringEndsWith("(CAST('7' AS CHAR), DEFAULT)", $projected);
    }

    public function testIgnoreRowsSkipsCompleteRecordsWithoutARequiredPrefix(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "header\n1\tAlice\n2\tBob\n";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(50))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items IGNORE 1 LINES";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringNotContainsString('header', $projected);
        self::assertStringContainsString("CAST('1' AS CHAR), CAST('Alice' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('2' AS CHAR), CAST('Bob' AS CHAR)", $projected);
        self::assertSame(1, substr_count($projected, '), ('));
    }

    public function testNoIgnoreClauseKeepsEveryRecord(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "1\tAlice\n2\tBob\n";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(50))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringContainsString("CAST('1' AS CHAR), CAST('Alice' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('2' AS CHAR), CAST('Bob' AS CHAR)", $projected);
        self::assertSame(1, substr_count($projected, '), ('));
    }

    public function testDefaultTargetsPreserveValuePositionsAroundGeneratedColumns(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(8, fwrite($stream, "1\tAlice\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, doubled INT AS (id * 2), name VARCHAR(50))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringStartsWith('INSERT INTO `items` (`id`, `name`) VALUES ', $projected);
        self::assertStringEndsWith("(CAST('1' AS CHAR), CAST('Alice' AS CHAR))", $projected);
        self::assertStringNotContainsString('`doubled`', $projected);
    }

    public function testProjectsEmptyInputAsAnEmptySelect(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse('CREATE TABLE items (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' IGNORE INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertSame('INSERT IGNORE INTO `items` (`id`) SELECT NULL AS `id` WHERE FALSE', $projected);
    }

    public function testRejectsUnreadableInputBeforeProjection(): void
    {
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse('CREATE TABLE items (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA LOCAL INFILE '/path/that/does/not/exist' INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('input file is not readable');

        (new MySqlLoadDataProjector($registry))->project($sql, $statement);
    }

    public function testRejectsGeneratedColumnTargets(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(2, fwrite($stream, "1\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, doubled INT AS (id * 2))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items (doubled)";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('generated column');

        (new MySqlLoadDataProjector($registry))->project($sql, $statement);
    }

    public function testDecodesControlEscapesAndEscapedRecordTerminators(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $encoded = "1\tA\\0B\\bC\\nD\\rE\\tF\\ZG\\\\H\\\nJ\n";
        self::assertSame(strlen($encoded), fwrite($stream, $encoded));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT PRIMARY KEY, payload VARCHAR(255))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);
        $decoded = "A\0B\x08C\nD\rE\tF\x1aG\\H\nJ";

        self::assertStringContainsString("CONVERT(X'" . bin2hex($decoded) . "' USING utf8mb4)", $projected);
        self::assertSame(0, substr_count($projected, '), ('));
    }

    public function testDistinguishesQuotedNullAndDoubledEnclosures(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $encoded = "1,\"A\"\"B\",\"NULL\",NULL,\\N,A\\,B\n";
        self::assertSame(strlen($encoded), fwrite($stream, $encoded));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT, a VARCHAR(20), b VARCHAR(20), c VARCHAR(20), d VARCHAR(20), e VARCHAR(20))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\'";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringContainsString("CAST('A\"B' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('NULL' AS CHAR)", $projected);
        self::assertSame(2, substr_count($projected, ', NULL'));
        self::assertStringContainsString("CAST('A,B' AS CHAR)", $projected);
    }

    public function testProjectsEverySetTarget(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "1\talice\timport\n";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT, name VARCHAR(20), note VARCHAR(20), source VARCHAR(20))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items (id, @Raw, source) SET name = UPPER(@RAW), note = CONCAT(@raw, '!')";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringContainsString('`id`, `name`, `note`, `source`', $projected);
        self::assertStringContainsString("UPPER(CAST('alice' AS CHAR))", $projected);
        self::assertStringContainsString("CONCAT(CAST('alice' AS CHAR), '!')", $projected);
        self::assertStringEndsWith(", CAST('import' AS CHAR))", $projected);
    }

    public function testRejectsAMissingTargetBeforeReadingTheInput(): void
    {
        $parser = new MySqlParser();
        $statement = $parser->parseSingleLogicalStatement(
            "LOAD DATA INFILE '/path/that/does/not/exist' INTO TABLE items",
        );
        self::assertInstanceOf(LoadStatement::class, $statement);
        $statement->table = null;

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('Cannot resolve LOAD DATA target');

        (new MySqlLoadDataProjector(new TableDefinitionRegistry()))->project('LOAD DATA', $statement);
    }

    public function testRejectsAReadableDirectoryAsANonFileInput(): void
    {
        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse('CREATE TABLE items (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $path = sys_get_temp_dir();
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('LOAD DATA input file is not readable');

        (new MySqlLoadDataProjector($registry))->project($sql, $statement);
    }

    public function testEmptyExplicitColumnListHasNoTargetColumns(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(2, fwrite($stream, "1\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse('CREATE TABLE items (id INT PRIMARY KEY)');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items ()";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('LOAD DATA has no target columns');

        (new MySqlLoadDataProjector($registry))->project($sql, $statement);
    }

    public function testFieldStateHandlesFirstFieldEnclosuresAndFinalEscapeBoundaries(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "\"A\"\"\nB\",\"x\ny\"\n\"P\nQ\",r\nA\"B\nNULL,z\nA\\N,z\nE\\t\"F\nC\\t\nD\\";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (first_value VARCHAR(50), second_value VARCHAR(50))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\'";
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringContainsString("CAST('A\"\nB' AS CHAR), CAST('x\ny' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('P\nQ' AS CHAR), CAST('r' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('A\"B' AS CHAR), DEFAULT", $projected);
        self::assertStringContainsString('VALUES ', $projected);
        self::assertStringContainsString('(NULL, CAST(\'z\' AS CHAR))', $projected);
        self::assertStringContainsString("CAST('AN' AS CHAR), CAST('z' AS CHAR)", $projected);
        self::assertStringContainsString("CAST('E\t\"F' AS CHAR), DEFAULT", $projected);
        self::assertStringContainsString("CAST('C\t' AS CHAR), DEFAULT", $projected);
        self::assertStringContainsString("CONVERT(X'445c' USING utf8mb4) AS CHAR), DEFAULT", $projected);
        self::assertSame(7, substr_count($projected, '), ('));
    }

    public function testSetVariableSubstitutionIsForwardOnlyAndPreservesSystemVariables(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $contents = "1\talice\tv1\n";
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse(
            'CREATE TABLE items (id INT, name VARCHAR(100))',
        );
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path)
            . "' INTO TABLE items (id, @raw, @version)"
            . ' SET name = CONCAT(@@version, @raw, @ + @raw, @missing, @raw)';
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        $projected = (new MySqlLoadDataProjector($registry))->project($sql, $statement);

        self::assertStringContainsString(
            "CONCAT(@@version, CAST('alice' AS CHAR), @+ CAST('alice' AS CHAR),"
                . " @missing, CAST('alice' AS CHAR))",
            $projected,
        );
    }

    #[TestWith(["FIELDS TERMINATED BY ''", 'fixed-row'], 'empty field delimiter')]
    #[TestWith(["LINES TERMINATED BY ''", 'fixed-row'], 'empty line delimiter')]
    #[TestWith(["FIELDS ENCLOSED BY 'xx'", 'single-byte'], 'multi-byte enclosure')]
    #[TestWith(["FIELDS ESCAPED BY 'xx'", 'single-byte'], 'multi-byte escape')]
    #[TestWith(['PARTITION (p0)', 'PARTITION'], 'partition target')]
    #[TestWith(['CHARACTER SET latin1', 'CHARACTER SET'], 'character conversion')]
    #[TestWith(['(id, ID)', 'Duplicate'], 'duplicate target')]
    #[TestWith(['(missing)', 'Unknown'], 'unknown target')]
    public function testRejectsUnsupportedLoadDataShapes(string $clause, string $message): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        self::assertSame(2, fwrite($stream, "1\n"));
        $metadata = stream_get_meta_data($stream);
        $path = $metadata['uri'] ?? null;
        self::assertIsString($path);

        $parser = new MySqlParser();
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser($parser))->parse('CREATE TABLE items (id INT, name VARCHAR(20))');
        self::assertNotNull($definition);
        $registry->register('items', $definition);
        $sql = "LOAD DATA INFILE '" . str_replace("'", "''", $path) . "' INTO TABLE items " . $clause;
        $statement = $parser->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage($message);

        (new MySqlLoadDataProjector($registry))->project($sql, $statement);
    }
}

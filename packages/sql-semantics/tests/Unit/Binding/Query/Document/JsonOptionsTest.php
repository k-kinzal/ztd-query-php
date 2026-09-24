<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonOptions::class)]
#[Medium]
final class JsonOptionsTest extends TestCase
{
    public function testInputSeparatesTheDocumentExpressionFromItsFormat(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc FORMAT JSON, '$[*]' PASSING 1 AS p COLUMNS (id INT PATH '$.id')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Format::Json, $relation->table->document->format);
        self::assertSame('doc', $relation->table->document->expression->columnBinding()?->column->name);
        self::assertSame('p', $relation->table->passing[0]->name);
        self::assertNull($relation->table->passing[0]->input->format);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $relation->table->passing[0]->input->expression);
        self::assertSame('1', $relation->table->passing[0]->input->expression->text);
    }

    public function testFormatAndWrapperAndQuotesReadTheirDomains(): void
    {
        self::assertNull(JsonOptions::format(null));
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Default, JsonOptions::wrapper(null));
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Default, JsonOptions::quotes(null));
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT FORMAT JSON PATH '$.s' WITH CONDITIONAL ARRAY WRAPPER OMIT QUOTES ON SCALAR STRING)) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        $column = $relation->table->columns[0];
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $column);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Format::Json, $column->format);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Conditional, $column->wrapper);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Omit, $column->quotes);
    }

    public function testResponsesAndResponseSeparateEmptyFromErrorBehaviors(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (id INT PATH '$.id' DEFAULT '0' ON EMPTY ERROR ON ERROR)) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        $column = $relation->table->columns[0];
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $column);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse::class, $column->onEmpty);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Error, $column->onError);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::Default, $relation->table->onError);
    }

    public function testExistsAndTableErrorReadTheirDefaults(): void
    {
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::Default, JsonOptions::exists(null));
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::Default, JsonOptions::tableError(null));
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.e FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (e INT EXISTS PATH '$.e' UNKNOWN ON ERROR) ERROR ON ERROR) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        $column = $relation->table->columns[0];
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ExistsColumn::class, $column);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::Unknown, $column->onError);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::Error, $relation->table->onError);
    }

    public function testWrapperNormalizesTheArrayNoiseWordAndTheShortWithForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT PATH '$.s' WITH WRAPPER, u TEXT PATH '$.u' WITHOUT ARRAY WRAPPER, v TEXT PATH '$.v' WITH UNCONDITIONAL ARRAY WRAPPER, w TEXT PATH '$.w')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        [$short, $without, $unconditional, $absent] = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $short);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $without);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $unconditional);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $absent);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Unconditional, $short->wrapper);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Without, $without->wrapper);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Unconditional, $unconditional->wrapper);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Default, $absent->wrapper);
    }

    public function testQuotesReadsKeepAndOmitWithOrWithoutTheScalarStringSuffix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT PATH '$.s' KEEP QUOTES, u TEXT PATH '$.u' OMIT QUOTES ON SCALAR STRING, v TEXT PATH '$.v')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        [$keep, $omit, $absent] = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $keep);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $omit);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $absent);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Keep, $keep->quotes);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Omit, $omit->quotes);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Default, $absent->quotes);
    }

    public function testTableErrorReadsTheEmptyRowsResponse(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT PATH '$.s') EMPTY ON ERROR) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::EmptyRows, $relation->table->onError);
    }

    public function testTableErrorRejectsAResponseOutsideTheTableDomain(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::JsonOption->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT PATH '$.s') NULL ON ERROR) AS jt");
    }

    public function testTableErrorReadsEmptyArrayAsTheEmptyRowsResponse(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)'));
        $statement = $binder->bind("SELECT jt.s FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (s TEXT PATH '$.s') EMPTY ARRAY ON ERROR) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::EmptyRows, $relation->table->onError);
        self::assertStringContainsString('EMPTY ON ERROR', $statement->toString());
    }

    #[TestWith(["select json_query('{}' format json, '$' with array wrapper empty on empty error on error)", "SELECT JSON_QUERY('{}' FORMAT JSON, '$' WITH UNCONDITIONAL WRAPPER EMPTY ARRAY ON EMPTY ERROR ON ERROR)"])]
    #[TestWith(["select json_query('{}', '$' omit quotes on scalar string empty array on error)", "SELECT JSON_QUERY('{}', '$' OMIT QUOTES EMPTY ARRAY ON ERROR)"])]
    #[TestWith(["select json_query('{}', '$' keep quotes null on empty)", "SELECT JSON_QUERY('{}', '$' KEEP QUOTES NULL ON EMPTY)"])]
    #[TestWith(["select json_value('{}', '$' default 1 + 1 on empty default 2 on error)", "SELECT JSON_VALUE('{}', '$' DEFAULT (1 + 1) ON EMPTY DEFAULT 2 ON ERROR)"])]
    #[TestWith(["select json_exists('{}', '$' unknown on error)", "SELECT JSON_EXISTS('{}', '$' UNKNOWN ON ERROR)"])]
    #[TestWith(["select * from json_table('{}', '$' columns (a int path '$') error on error) as j", 'SELECT "j"."a" AS "a" FROM JSON_TABLE(\'{}\', \'$\' COLUMNS("a" integer PATH \'$\') ERROR ON ERROR) AS "j"'])]
    public function testOptionsReadLowercaseKeywords(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }
}

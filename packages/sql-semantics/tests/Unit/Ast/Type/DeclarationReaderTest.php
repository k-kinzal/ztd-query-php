<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Ast\Type\DeclarationReader::class)]
#[Medium]
final class DeclarationReaderTest extends TestCase
{
    #[TestWith(['numeric(ARRAY[1])'])]
    #[TestWith(['numeric(ARRAY[1]) ARRAY[4]'])]
    #[TestWith(['app.measure(ARRAY[1])'])]
    #[TestWith(['app.measure(ARRAY[1]) ARRAY[4]'])]
    public function testReadDoesNotMistakeAnArrayExpressionForTypeDimensions(string $declaration): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('type modifiers');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x ' . $declaration . ')');
    }

    public function testSqliteKeepsItsIndependentNumericDeclarationRules(): void
    {
        $type = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(x NUMERIC(12, 2))')->tables[0]->columns[0]->type;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\SqliteDeclaration::class, $type->identity);
        self::assertSame(\SqlSemantics\Type\Identity\StorageAffinity::Numeric, $type->identity->affinity);
        self::assertNotNull($type->identity->size);
        self::assertNotNull($type->identity->scale);
        self::assertSame('12', $type->identity->size->spelling);
        self::assertSame('2', $type->identity->scale->spelling);
    }

}

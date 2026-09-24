<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\SchemaObjectIdentity::class)]
#[Medium]
final class SchemaObjectIdentityTest extends TestCase
{
    #[TestWith(['ALTER COLLATION app.german RENAME TO deutsch', Kind\SchemaObjectKind::Collation])]
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY app.german RENAME TO deutsch', Kind\SchemaObjectKind::TextSearchDictionary])]
    #[TestWith(['ALTER STATISTICS app.german RENAME TO deutsch', Kind\SchemaObjectKind::Statistics])]
    public function testRetainsTheObjectClassAndQualifiedName(string $sql, Kind\SchemaObjectKind $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\RenameObjectStatement::class, $statement);
        self::assertEquals(new Catalog\SchemaObjectIdentity($kind, new QualifiedName(['app', 'german'])), $statement->object);
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['db', 'app', 'german']));
    }
}

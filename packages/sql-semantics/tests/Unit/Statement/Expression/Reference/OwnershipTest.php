<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(Ownership::class)]
#[Small]
final class OwnershipTest extends TestCase
{
    public function testAcceptsQueriesButRejectsDetachedInnerReferences(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $body = new ScopedSelect($outer, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('missing'))))));
        $inner = $body->scope;
        $reference = $body->fields()->at(0)->expression;
        self::assertInstanceOf(ColumnReference::class, $reference);
        $query = new SqliteExists(new SqliteSubquery($outer, $body));
        $ownership = new Ownership();
        self::assertTrue($ownership->accepts($query, $outer));
        self::assertFalse($ownership->accepts($reference, $outer));
        self::assertFalse($ownership->accepts($query, $inner));
        self::assertTrue($ownership->accepts($reference, $inner));
    }

}

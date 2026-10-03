<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Select;
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
        $inner = new Scope($outer);
        $reference = new ColumnReference($inner, new Name('missing'));
        $query = new SqliteExists(new SqliteSubquery($outer, new Select(new Fields($inner, new Field($reference)))));
        $ownership = new Ownership();
        self::assertTrue($ownership->accepts($query, $outer));
        self::assertFalse($ownership->accepts($reference, $outer));
        self::assertFalse($ownership->accepts($query, $inner));
        self::assertTrue($ownership->accepts($reference, $inner));
    }

}

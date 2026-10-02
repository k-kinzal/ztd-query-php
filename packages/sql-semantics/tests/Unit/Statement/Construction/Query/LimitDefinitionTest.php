<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Query;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\Query\LimitDefinition::class)]
#[Small]
final class LimitDefinitionTest extends TestCase
{
    public function testCommaNotationRetainsOffsetThenCountRoles(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($context, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new E\SqliteInteger(new UnsignedInteger('7')))), limit: new C\Query\LimitDefinition(new E\SqliteInteger(new UnsignedInteger('1')), new E\SqliteInteger(new UnsignedInteger('1')), true)));
        $result = (new PDO('sqlite::memory:'))->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([], $result->fetchAll(PDO::FETCH_NUM));
        self::assertSame('LIMIT 1, 1', $query->limit?->toString());
    }
}

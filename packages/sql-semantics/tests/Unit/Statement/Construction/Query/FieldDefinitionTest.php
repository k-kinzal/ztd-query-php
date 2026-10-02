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

#[CoversClass(C\Query\FieldDefinition::class)]
#[Small]
final class FieldDefinitionTest extends TestCase
{
    public function testDuplicateLabelsRetainBothOutputPositions(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($context, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new E\SqliteInteger(new UnsignedInteger('1')), new Name('same')), new C\Query\FieldDefinition(new E\SqliteInteger(new UnsignedInteger('2')), new Name('same')))));
        self::assertSame(2, $query->fields()->count());
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\AmbiguousFields::class, $query->lookupField('same'));
        $result = (new PDO('sqlite::memory:'))->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([1, 2], $result->fetch(PDO::FETCH_NUM));
    }
}

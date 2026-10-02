<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\ColumnConstruction::class)]
#[Small]
final class ColumnConstructionTest extends TestCase
{
    public function testDeriveKeepsMissingColumnsDistinctFromMissingDeclarations(): void
    {
        $complete = new Catalog(new SearchPath(new Name('main')));
        $open = new Catalog(new SearchPath(new Name('main')), complete: false);
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'));
        $closedScope = new \SqlSemantics\Statement\Relation\Scope($complete, new \SqlSemantics\Statement\Relation\TableReference($complete, $name));
        $openScope = new \SqlSemantics\Statement\Relation\Scope($open, new \SqlSemantics\Statement\Relation\TableReference($open, $name));
        $use = new C\Expression\ColumnUse(new Name('id'));
        self::assertSame(\SqlSemantics\Statement\Type\Invalid::MissingColumn, (new C\ColumnConstruction())->derive($use, $closedScope)->type());
        self::assertSame(\SqlSemantics\Statement\Type\Unresolved::MissingDeclaration, (new C\ColumnConstruction())->derive($use, $openScope)->type());
    }
}

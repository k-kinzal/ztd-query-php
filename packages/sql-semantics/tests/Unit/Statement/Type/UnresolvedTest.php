<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(Unresolved::class)]
#[Small]
final class UnresolvedTest extends TestCase
{
    public function testTypeRemainsUnknownWhenTheColumnDeclarationIsNotProvided(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $table = new TableReference($catalog, new QualifiedName(new Name('users')));
        $column = new ColumnReference(new Scope($catalog, $table), new Name('id'));
        self::assertSame(Unresolved::MissingDeclaration, $column->type());
    }
}

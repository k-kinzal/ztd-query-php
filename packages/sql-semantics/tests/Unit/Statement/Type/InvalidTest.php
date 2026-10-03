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
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(Invalid::class)]
#[Small]
final class InvalidTest extends TestCase
{
    public function testACompleteContextMakesAnAbsentColumnInvalid(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $table = new TableReference($catalog, new QualifiedName(new Name('users')));
        $column = new ColumnReference(new Scope($catalog, $table), new Name('id'));
        self::assertSame(Invalid::MissingColumn, $column->type());
    }
}

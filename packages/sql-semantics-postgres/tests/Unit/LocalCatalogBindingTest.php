<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\LocalCatalogBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;

#[CoversClass(LocalCatalogBinding::class)]
#[Small]
final class LocalCatalogBindingTest extends TestCase
{
    public function testCheckAcceptsOneExactCatalogAcrossDecodedRoles(): void
    {
        $current = new Name('reporting.sales');
        $schema = new Name('app');
        $table = new Name('monthly');
        $binding = new LocalCatalogBinding();

        $binding->check([
            new QualifiedName($table, $schema, $current),
            new ColumnReference([$current, $schema, $table, new Name('total')]),
            new ColumnStar([$current, $schema, $table]),
            new QualifiedName(new Name('local.table')),
        ], $current);

        $this->addToAssertionCount(1);
    }
}

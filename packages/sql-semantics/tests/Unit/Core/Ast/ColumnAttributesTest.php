<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Ast\ColumnAttributes;
use SqlSemantics\Statement\Declaration\Nullability;

#[CoversClass(ColumnAttributes::class)]
#[Medium]
final class ColumnAttributesTest extends TestCase
{
    public function testNullsKeepExplicitConstraintOrderAndIgnoreDefaults(): void
    {
        $language = new \SqlSemantics\Core\Language(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $tree = $language->parser()->parse('CREATE TABLE t(a INT NOT NULL CONSTRAINT n NULL DEFAULT NULL)');
        $attributes = \SqlSemantics\Core\Ast\Tree::outer($tree, ['ColConstraint']);
        self::assertSame([Nullability::NotNull, Nullability::MaybeNull], ColumnAttributes::nulls($attributes));
    }

}

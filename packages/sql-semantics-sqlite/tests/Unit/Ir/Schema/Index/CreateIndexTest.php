<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexTerm;

#[CoversClass(CreateIndex::class)]
#[Small]
final class CreateIndexTest extends TestCase
{
    public function testToStringUsesDetachedKeysAndResolvedSchema(): void
    {
        $code = new Identifier('project code');
        $first = new IndexTerm(new CollatedExpression(new ColumnReference(1), Collation::NoCase), true);
        $terms = [&$first, new IndexTerm(new ColumnReference(0))];
        $where = new Comparison(ComparisonOperator::Greater, new ColumnReference(0), new IntegerLiteral('2'));
        $value = new CreateIndex(
            new Identifier('temp'),
            new Identifier('ix"one'),
            new Identifier('items'),
            [new Identifier('id'), &$code, new Identifier('ignored')],
            $terms,
            $where,
            unique: true,
            ifNotExists: true
        );
        $first = new IndexTerm(new ColumnReference(0));
        $code = new Identifier('other');

        self::assertSame([0 => 'id', 1 => 'project code'], array_map(static fn (Identifier $name): string => $name->value, $value->bindings));
        self::assertSame('CREATE UNIQUE INDEX IF NOT EXISTS "temp"."ix""one" ON "items" (("project code" COLLATE NOCASE) DESC, "id" ASC) WHERE ("id" > 2)', $value->toString());
    }

    public function testConstantKeyHasNoColumnBindings(): void
    {
        $value = new CreateIndex(
            new Identifier('main'),
            new Identifier('ix'),
            new Identifier('items'),
            [new Identifier('unused')],
            [new IndexTerm(new NullLiteral())]
        );

        self::assertSame([], $value->bindings);
    }

    public function testProfileIdIdentifiesTheFixedLanguage(): void
    {
        $value = new CreateIndex(
            new Identifier('main'),
            new Identifier('ix'),
            new Identifier('items'),
            [new Identifier('id')],
            [new IndexTerm(new ColumnReference(0))]
        );

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}

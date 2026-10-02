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

#[CoversClass(C\SubqueryConstruction::class)]
#[Small]
final class SubqueryConstructionTest extends TestCase
{
    public function testDeriveKeepsACorrelatedBodySeparateFromAStatementRoot(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))));
        $source = (new C\SubqueryConstruction())->derive($input, $outer);
        self::assertSame($outer, $source->scope);
        self::assertSame($outer, $source->query->scope->parent);
        self::assertInstanceOf(\SqlSemantics\Statement\Query\ScopedSelect::class, $source->query);
        self::assertNotInstanceOf(\SqlSemantics\Statement\Operation::class, $source->query);
        self::assertSame('SELECT id', $source->query->toString());
    }
}

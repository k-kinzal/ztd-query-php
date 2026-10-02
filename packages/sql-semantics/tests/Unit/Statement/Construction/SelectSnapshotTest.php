<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\SelectSnapshot::class)]
#[Small]
final class SelectSnapshotTest extends TestCase
{
    public function testInputsCreateFreshOutputsAndResolveTheActualWhereAlias(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new E\NullConstant(), new Name('answer'))), where: new C\Expression\ColumnUse(new Name('answer')));
        $snapshot = new C\SelectSnapshot($context, $input);
        self::assertSame($context, $snapshot->scope->catalog);
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\AliasReference::class, $snapshot->where);
        self::assertSame($snapshot->projection->field('answer'), $snapshot->where->field);
        self::assertSame(\SqlSemantics\Statement\Type\NullDomain::Null, $snapshot->where->type());
    }
}

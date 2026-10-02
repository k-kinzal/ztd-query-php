<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(NamedAlias::class)]
#[Small]
final class NamedAliasTest extends TestCase
{
    public function testKeepsExactProjectionAndFieldIdentity(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $field = new Field(new NullConstant(), new Name('answer'));
        $fields = new Fields($outer, $field);
        $alias = new NamedAlias($fields, $field, new Name('answer'));
        self::assertSame($field, $alias->field);
        self::assertSame($fields, $alias->projection);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($alias));
    }

}

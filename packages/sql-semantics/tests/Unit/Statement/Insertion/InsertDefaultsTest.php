<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Insertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\Arity;
use SqlSemantics\Statement\Insertion\InsertDefaults;
use SqlSemantics\Statement\Insertion\Target;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(InsertDefaults::class)]
#[Small]
final class InsertDefaultsTest extends TestCase
{
    public function testToStringPreservesTheDistinctSourceFormAndItsTarget(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))), new Name('foo'));
        $scope = new Scope($catalog);
        $operation = new InsertDefaults($target);
        self::assertSame('INSERT INTO bar (foo) DEFAULT VALUES', $operation->toString());
        self::assertSame($target, $operation->target);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));

    }
    public function testArityDistinguishesImplicitDefaultsFromAnExplicitColumnMismatch(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('bar')));
        self::assertSame(Arity::Matching, (new InsertDefaults(new Target($relation)))->arity());
        self::assertSame(Arity::Mismatch, (new InsertDefaults(new Target($relation, new Name('foo'))))->arity());
    }

}

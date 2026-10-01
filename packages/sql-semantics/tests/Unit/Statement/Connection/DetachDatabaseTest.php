<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Connection\DetachDatabase;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(DetachDatabase::class)]
#[Small]
final class DetachDatabaseTest extends TestCase
{
    public function testToStringDoesNotRequireOrRemoveAnAttachedDatabase(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $schema = new SqliteText(new StringLiteral('extra'));
        $request = new DetachDatabase($scope, $schema);
        self::assertSame($schema, $request->schema);
        self::assertSame("DETACH DATABASE 'extra'", $request->toString());
        self::assertSame(['main'], array_map(static fn (Name $name): string => $name->value, $scope->catalog->searchPath->schemas));
        self::assertTrue((new SemanticGraph())->isSemanticOperation($request));
    }
}

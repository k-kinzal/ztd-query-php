<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Connection\AttachDatabase;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(AttachDatabase::class)]
#[Small]
final class AttachDatabaseTest extends TestCase
{
    public function testToStringKeepsAllThreeIndependentOperands(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $filename = new SqliteText(new StringLiteral(':memory:'));
        $schema = new SqliteText(new StringLiteral('extra'));
        $key = new SqliteText(new StringLiteral('secret'));
        $request = new AttachDatabase($scope, $filename, $schema, $key);
        self::assertSame($filename, $request->filename);
        self::assertSame($schema, $request->schema);
        self::assertSame($key, $request->key);
        self::assertSame("ATTACH DATABASE ':memory:' AS 'extra' KEY 'secret'", $request->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($request));
        self::assertSame([], $scope->catalog->tables);
    }

}

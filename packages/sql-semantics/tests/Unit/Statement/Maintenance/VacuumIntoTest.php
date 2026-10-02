<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Maintenance\VacuumInto;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(VacuumInto::class)]
#[Small]
final class VacuumIntoTest extends TestCase
{
    public function testToStringKeepsTheCopyDestinationDistinctFromTheSource(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $destination = new SqliteText(new StringLiteral('copy.db'));
        $request = new VacuumInto($scope, $destination);
        self::assertSame($destination, $request->destination);
        self::assertSame('main', $request->schema->value);
        self::assertSame("VACUUM main INTO 'copy.db'", $request->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($request));
    }

    public function testIsNoOpIdentifiesTheIgnoredTemporaryDatabase(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $request = new VacuumInto($scope, new SqliteText(new StringLiteral('unused.db')), new Name('TEMP'));
        self::assertTrue($request->isNoOp());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Maintenance\Vacuum;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Vacuum::class)]
#[Small]
final class VacuumTest extends TestCase
{
    public function testToStringIdentifiesAnInPlaceRebuild(): void
    {
        $schema = new Name('attached');
        $request = new Vacuum($schema);
        self::assertSame($schema, $request->schema);
        self::assertSame('VACUUM attached', $request->toString());
        self::assertSame('main', (new Vacuum())->schema->value);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($request));
    }
    public function testIsNoOpIdentifiesTheIgnoredTemporaryDatabase(): void
    {
        $request = new Vacuum(new Name('TEMP'));
        self::assertFalse((new Vacuum())->isNoOp());
        self::assertTrue($request->isNoOp());
    }
}

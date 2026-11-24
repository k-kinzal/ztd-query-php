<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;

#[CoversClass(RelationTarget::class)]
#[Small]
final class RelationTargetTest extends TestCase
{
    public function testQuotedUsesAnAbsoluteEmptyNamedAttachment(): void
    {
        $relation = new RelationTarget(new Identifier(''), new Identifier('quoted "view"'), ExternalRelationKind::View);

        self::assertSame('""."quoted ""view"""', $relation->quoted());
        self::assertSame(ExternalRelationKind::View, $relation->kind);
    }
}

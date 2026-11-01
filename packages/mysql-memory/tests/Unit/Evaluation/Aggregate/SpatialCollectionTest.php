<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Aggregate\SpatialCollection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialCollection::class)]
#[Small]
final class SpatialCollectionTest extends TestCase
{
    public function testResultRetainsMixedMembersWithoutFlattening(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $fold = new SpatialCollection();
        $point = new Geometry(1, [[1.0, 2.0]]);
        $empty = new Geometry(7);
        self::assertNull($fold->result());
        $fold->add($frame, Wkb::write($point));
        self::assertSame(Wkb::write(new Geometry(4, [], [$point])), $fold->result());
        $fold->add($frame, Wkb::write($empty));
        self::assertSame(Wkb::write(new Geometry(7, [], [$point, $empty])), $fold->result());
    }

    public function testAddRejectsDifferentReferenceSystems(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $fold = new SpatialCollection();
        $fold->add($frame, Wkb::write(new Geometry(1, [[1.0, 2.0]])));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4034);
        $this->expectExceptionMessage('different SRIDs: 0 and 4326');
        $fold->add($frame, Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 4326)));
    }

    public function testAddRejectsAnUnknownReferenceSystem(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3548);
        (new SpatialCollection())->add($frame, Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 999999)));
    }

    public function testAddRejectsMalformedGeometry(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3037);
        (new SpatialCollection())->add($frame, 'invalid');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class TypeDeclarationTest extends TestCase
{
    public function testExtractTypeStopsAtConstraints(): void
    {
        self::assertSame('VARCHAR', (new Subject())->extractType('varchar(10) NOT NULL'));
    }

    public function testParseExtractsNumericAndStringDimensions(): void
    {
        $decimal = (new Subject())->parse('NUMERIC(8, 2)');
        self::assertSame('NUMERIC', $decimal->type);
        self::assertSame(8, $decimal->precision);
        self::assertSame(2, $decimal->scale);
        self::assertSame(10, (new Subject())->parse('VARCHAR(10)')->length);
    }

    public function testParseDoesNotConsumeAConstraintAsATypeParameter(): void
    {
        $shape = (new Subject())->parse('INTEGER CHECK (value IN (1, 2))');
        self::assertSame('INTEGER', $shape->type);
        self::assertNull($shape->length);
        self::assertNull($shape->precision);
        self::assertNull($shape->scale);
        self::assertSame('BLOB', (new Subject())->extractType(''));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ProgramRow::class)]
#[Small]
final class ProgramRowTest extends TestCase
{
    public function testPositionFindsTheFirstNameWithoutRegardToCase(): void
    {
        $row = new ProgramRow(new ParameterList([]), [new Name('a'), new Name('B'), new Name('b')], [Domain::integer(), Domain::integer(), Domain::integer()]);

        self::assertSame([1, null], [$row->position(new Name('b')), $row->position(new Name('c'))]);
    }
}

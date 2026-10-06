<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\StringResult;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(StringResult::class)]
#[Small]
final class StringResultTest extends TestCase
{
    public function testConcatenationIsBinaryWhenAnOperandIs(): void
    {
        $results = new StringResult();
        $bytes = new Known(new Binary(BinaryKind::Blob));
        $number = new Known(new Integral(IntegralKind::Int));

        self::assertEquals(new Known(new Binary(BinaryKind::VarBinary)), $results->concatenation([$number, $bytes]));
        self::assertEquals(new Known(new Character(CharacterKind::VarChar)), $results->concatenation([$number, new NullOnly()]));
        self::assertEquals(new Choice([new Character(CharacterKind::VarChar), new Binary(BinaryKind::VarBinary)]), $results->concatenation([$number, new Choice([new Binary(BinaryKind::Blob), new Integral(IntegralKind::Int)])]));
    }
}

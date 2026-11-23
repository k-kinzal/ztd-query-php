<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BooleanInput;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

#[CoversClass(BooleanInput::class)]
#[Small]
final class BooleanInputTest extends TestCase
{
    public function testUntypedDecodesTheWholeStringConstant(): void
    {
        $input = new Constant(new StringConstant("\t Ye\r"));
        self::assertTrue((new BooleanInput())->untyped($input)?->value);
    }

    public function testFromTextRecognizesBothTruthValues(): void
    {
        $input = new BooleanInput();
        self::assertFalse($input->fromText(" \tOF\n")->value);
        self::assertTrue($input->fromText('tr')->value);
    }
}

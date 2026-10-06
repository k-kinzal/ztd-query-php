<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Weight;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(WeightLevelRange::class)]
#[Small]
final class WeightLevelRangeTest extends TestCase
{
    public function testRenderWritesTheRange(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new WeightLevelRange(new Numeral('1'), new Numeral('3')))->render($out);

        self::assertSame('1 - 3', (new Lexical())->join($out->pieces()));
    }
}

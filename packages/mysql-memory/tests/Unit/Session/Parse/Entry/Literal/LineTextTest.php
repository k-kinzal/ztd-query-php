<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Literal;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Literal\LineText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(LineText::class)]
#[Small]
final class LineTextTest extends TestCase
{
    public function testErrorUsesTheDecodedPrefixAndReleaseBound(): void
    {
        $modern = (new Instance())->connect();
        $modern->text = 'unrelated invalid input';
        $legacy = (new Instance('5.7.44'))->connect();
        $rule = new LineText();
        self::assertSame("Incorrect argument contains not-allowed LF value: 'a\nb'", $rule->error(new Text("a\nb\0tail"), $modern)->getMessage());
        $value = new Text(str_repeat('x', 140) . "\n");
        self::assertSame("Incorrect argument contains not-allowed LF value: '" . str_repeat('x', 128) . "'", $rule->error($value, $modern)->getMessage());
        self::assertSame("Incorrect argument contains not-allowed LF value: '" . str_repeat('x', 128) . "'", $rule->error($value, $legacy)->getMessage());
        self::assertSame('unrelated invalid input', $modern->text);
    }
}

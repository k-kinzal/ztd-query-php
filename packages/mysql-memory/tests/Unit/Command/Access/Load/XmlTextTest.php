<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\XmlText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlText::class)]
#[Small]
final class XmlTextTest extends TestCase
{
    public function testAttributesDistinguishesEmptySpacedAndUnquotedFields(): void
    {
        $text = new XmlText();
        self::assertSame(['a' => '1', 'b' => '&', 'c' => null, 'd' => null], $text->attributes('<row a="1" b="&amp;" c="" d = "ignored"/>'));
        self::assertNull($text->attributes('<row a=1/>'));
    }

    public function testContentIncludesModernCdataWithoutDecodingItsEntities(): void
    {
        $input = '  x&amp;<![CDATA[&amp;]]>&amp;y</b>';
        self::assertSame(['x&&amp;&y', 25], (new XmlText())->content($input, 0, false));
        self::assertSame(['x&', 0], (new XmlText())->content($input, 0, true));
    }

    public function testDecodePreservesNumericReferencesAndDecodesOnlyOnce(): void
    {
        self::assertSame('<>&"\' &lt; &#65; &missing;', (new XmlText())->decode('&lt;&gt;&amp;&quot;&apos; &amp;lt; &#65; &missing;'));
    }
}

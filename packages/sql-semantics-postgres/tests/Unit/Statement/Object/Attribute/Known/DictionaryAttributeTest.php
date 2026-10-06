<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(DictionaryAttribute::class)]
#[Small]
final class DictionaryAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::TextSearchTemplate], [DictionaryAttribute::Template->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([DictionaryAttribute::Template, null], [DictionaryAttribute::named('template'), DictionaryAttribute::named('TEMPLATE')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('template', DictionaryAttribute::Template->text());
    }
}

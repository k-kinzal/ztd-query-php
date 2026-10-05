<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ConfigurationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(ConfigurationAttribute::class)]
#[Small]
final class ConfigurationAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::TextSearchParser, Reading::TextSearchConfiguration], [ConfigurationAttribute::Parser->reading(), ConfigurationAttribute::Copy->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([ConfigurationAttribute::Parser, null], [ConfigurationAttribute::named('parser'), ConfigurationAttribute::named('PARSER')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('parser', ConfigurationAttribute::Parser->text());
    }
}

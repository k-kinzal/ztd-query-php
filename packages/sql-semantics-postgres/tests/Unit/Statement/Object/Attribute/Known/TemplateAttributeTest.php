<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TemplateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(TemplateAttribute::class)]
#[Small]
final class TemplateAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Function, Reading::Function], array_map(static fn (TemplateAttribute $attribute): Reading => $attribute->reading(), TemplateAttribute::cases()));
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([TemplateAttribute::Init, null], [TemplateAttribute::named('init'), TemplateAttribute::named('INIT')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('init', TemplateAttribute::Init->text());
    }
}

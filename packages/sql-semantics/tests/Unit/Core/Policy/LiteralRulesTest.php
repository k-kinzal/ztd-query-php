<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Core\Policy\LiteralRules::class)]
#[Medium]
final class LiteralRulesTest extends TestCase
{
    public function testDecodeUsesTheLanguageMode(): void
    {
        $language = new \SqlSemantics\Core\Language(\SqlSemantics\Platform\MySql\Dialect::MySql, mode: \SqlSemantics\Platform\MySql\Mode::fromString('NO_BACKSLASH_ESCAPES'));
        $tokens = array_values(array_filter($language->parser()->tokenize("'a\\nb'"), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        $rules = $language->dialect->platform()->literals($language);
        self::assertSame('a\\nb', $rules->decode($tokens)->value());
    }

}

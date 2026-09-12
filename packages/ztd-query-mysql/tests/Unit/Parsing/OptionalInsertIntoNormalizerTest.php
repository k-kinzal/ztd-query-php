<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer;

#[CoversClass(OptionalInsertIntoNormalizer::class)]
final class OptionalInsertIntoNormalizerTest extends TestCase
{
    public function testNormalizeOptionalInsertIntoPreservesModifiers(): void
    {
        $normalizer = new OptionalInsertIntoNormalizer();
        self::assertSame('INSERT IGNORE INTO users VALUES (1)', $normalizer->normalizeOptionalInsertInto('INSERT IGNORE users VALUES (1)'));
        self::assertSame('INSERT INTO users VALUES (1)', $normalizer->normalizeOptionalInsertInto('INSERT INTO users VALUES (1)'));
        self::assertSame('SELECT 1', $normalizer->normalizeOptionalInsertInto('SELECT 1'));
        self::assertSame('', $normalizer->normalizeOptionalInsertInto(''));
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Semantics\Keyword::class)]
#[Small]
final class KeywordTest extends TestCase
{
    public function testSourceBelongsToTheContainingClause(): void
    {
        $keyword = new class () {
            use \SqlSemantics\Statement\Semantics\Keyword;
        };
        self::assertNull($keyword->source());
    }

    public function testHasBindingIsFalseForASharedKeyword(): void
    {
        $keyword = new class () {
            use \SqlSemantics\Statement\Semantics\Keyword;
        };
        self::assertFalse($keyword->hasBinding());
    }
}

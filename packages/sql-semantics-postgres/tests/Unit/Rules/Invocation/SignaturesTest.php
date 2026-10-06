<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Signatures;

#[CoversClass(Signatures::class)]
#[Small]
final class SignaturesTest extends TestCase
{
    public function testRowsListsTheCatalogAndGeneratedRows(): void
    {
        $signatures = new Signatures();
        self::assertSame([['int8', 'a', 'N', []]], $signatures->rows('count'));
        self::assertContains(['int8', 'a', 'Y', ['int4']], $signatures->rows('sum'));
        self::assertContains(['date', 'a', 'Y', ['date']], $signatures->rows('max'));
        self::assertSame([], $signatures->rows('no_such_function'));
    }
}

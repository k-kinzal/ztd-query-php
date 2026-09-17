<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\Epilogue;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Epilogue::class)]
#[UsesClass(Location::class)]
#[Small]
final class EpilogueTest extends TestCase
{
    public function testCode(): void
    {
        $epilogue = new Epilogue("\nint main() {}\n", new Location(9, 3));

        self::assertSame("\nint main() {}\n", $epilogue->code);
        self::assertSame('9:3', (string) $epilogue->location);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;

#[CoversClass(Path::class)]
#[Small]
final class PathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerReadRefusesAnInvalidPathAtThePositionTheServerNames(): iterable
    {
        yield 'empty' => ['', 0];
        yield 'no scope' => ['bad', 1];
        yield 'leg without dot' => ['$a', 1];
        yield 'missing name' => ['$.', 2];
        yield 'name not an identifier' => ['$.a-b', 5];
        yield 'unterminated quoted name' => ['$."a', 4];
        yield 'unclosed cell' => ['$[0', 3];
        yield 'character before the bracket' => ['$[1.5]', 4];
        yield 'negative index' => ['$[-1]', 2];
        yield 'index too big' => ['$[4294967296]', 2];
        yield 'range backwards' => ['$[2 to 1]', 8];
        yield 'to without space' => ['$[0 to1]', 5];
        yield 'last minus nothing' => ['$[last - ]', 9];
        yield 'lone star' => ['$*', 2];
        yield 'two stars at the end' => ['$**', 3];
        yield 'three stars' => ['$***', 3];
        yield 'two stars after a name' => ['$.a**', 5];
        yield 'trailing character' => ['$[0]x', 4];
    }

    #[DataProvider('providerReadRefusesAnInvalidPathAtThePositionTheServerNames')]
    public function testReadRefusesAnInvalidPathAtThePositionTheServerNames(string $path, int $position): void
    {
        $value = Path::read($path);
        self::assertInstanceOf(Invalid::class, $value);
        self::assertSame($position, $value->position);
    }

    public function testReadReadsEveryKindOfLeg(): void
    {
        $path = Path::read(' $ .a ."b c" .* [ 1 ] [last - 2] [*] [0 to last] **.d ');
        self::assertInstanceOf(Path::class, $path);

        self::assertSame([Kind::Member, Kind::Member, Kind::AnyMember, Kind::Cell, Kind::Cell, Kind::AnyCell, Kind::Range, Kind::Descendants, Kind::Member], array_map(static fn ($leg): Kind => $leg->kind, $path->legs));
        self::assertNotNull($path->legs[4]->from);
        self::assertNotNull($path->legs[6]->to);
        self::assertSame(['a', 'b c', [true, 2], [true, 0]], [$path->legs[0]->name, $path->legs[1]->name, [$path->legs[4]->from->last, $path->legs[4]->from->offset], [$path->legs[6]->to->last, $path->legs[6]->to->offset]]);
    }

}

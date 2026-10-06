<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Expansion;

use Deriver\Model\Expansion\Request as Subject;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RequestTest extends TestCase
{
    public function testInputDemandsOnlyTheSelectedOperand(): void
    {
        $seen = [];
        $request = new Subject('binary', '+', new \Deriver\Reference\SourceRef('s', 'a.php', 0, 3), static function (int|string $key) use (&$seen): Term {
            $seen[] = $key;
            return Term::constant($key);
        });
        self::assertSame(1, $request->input(1)->native());
        self::assertSame([1], $seen);
    }

}

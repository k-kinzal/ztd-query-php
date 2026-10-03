<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CommentsTest extends TestCase
{
    public function testWithinKeepsNestedStatementLocationsWithoutCompilingBodies(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function f(){if(true){/** @var int $n */ $n=1;}}');
        $comments = (new \Deriver\Source\Declaration\Comments())->within($index, 'f');
        self::assertCount(1, $comments);
        self::assertSame('/** @var int $n */', $comments[0]->text);
        self::assertSame(0, $index->graphCount());
        self::assertSame([], (new \Deriver\Source\Declaration\Comments())->within($index, 'missing'));
    }
}

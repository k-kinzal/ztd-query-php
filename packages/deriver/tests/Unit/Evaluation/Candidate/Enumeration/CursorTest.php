<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Enumeration;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Enumeration\Cursor as Subject;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CursorTest extends TestCase
{
    public function testNextPrunesConflictingSelectors(): void
    {
        $inner = (new Choices())->make([[Term::constant(1),['x' => true]],[Term::constant(2),['x' => false]]]);
        $cursor = new Subject((new Choices())->make([[$inner,['x' => false]]]));
        $next = $cursor->next();
        self::assertNotNull($next);
        self::assertSame(2, $next[0]->native());
        self::assertNull($cursor->next());
    }

    public function testHasRemainingDoesNotConsumeTheFrontier(): void
    {
        $cursor = new Subject(Term::constant(1));
        self::assertTrue($cursor->hasRemaining());
        $cursor->next();
        self::assertFalse($cursor->hasRemaining());
    }

    public function testRemainderRetainsTheCurrentAndUnvisitedValues(): void
    {
        $cursor = new Subject((new Choices())->make([[Term::constant(1),[]],[Term::constant(2),[]]]));
        $current = $cursor->next();
        self::assertNotNull($current);
        $rest = $cursor->remainder($current);
        self::assertCount(2, iterator_to_array((new Choices())->alternatives($rest), false));
    }

}

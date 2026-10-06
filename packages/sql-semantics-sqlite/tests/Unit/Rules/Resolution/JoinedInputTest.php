<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\JoinedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(JoinedInput::class)]
#[Small]
final class JoinedInputTest extends TestCase
{
    public function testFactAndVisibleRelationsAreKeptAsGiven(): void
    {
        $input = new TableInput(new QualifiedName(new Name('t')));
        $fact = new RelationFact(new RowShape([]));
        $visible = new VisibleRelation($input, $fact->shape, null, $input->name);
        $joined = new JoinedInput($fact, [$visible]);

        self::assertSame($fact, $joined->fact);
        self::assertSame([$visible], $joined->visible);
        self::assertSame([], (new JoinedInput($fact, []))->visible);
    }
}

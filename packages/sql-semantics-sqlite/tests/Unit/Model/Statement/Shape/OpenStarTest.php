<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OpenStar;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(OpenStar::class)]
#[Medium]
final class OpenStarTest extends TestCase
{
    public function testMissingNamesTheUndeclaredRelationBehindAStar(): void
    {
        $fact = (new Inspection())->inspect('SELECT t.*, u.a FROM t, u')->facts->output;

        self::assertNotNull($fact);
        self::assertInstanceOf(OpenStar::class, $fact->projection[0]);
        self::assertInstanceOf(Field::class, $fact->projection[1]);
        self::assertInstanceOf(UndeclaredRelation::class, $fact->projection[0]->missing[0]);
        self::assertSame('t', $fact->projection[0]->missing[0]->name->name->value);
    }

    public function testMissingNamesTheIncompleteMemberListOfADeclaredRelation(): void
    {
        $semantics = new Inspection();
        $view = $semantics->inspect('CREATE VIRTUAL TABLE v USING fts5(a)');

        $fact = $semantics->inspect('SELECT * FROM v', [$view])->facts->output;

        self::assertNotNull($fact);
        self::assertInstanceOf(OpenStar::class, $fact->projection[0]);
        self::assertInstanceOf(IncompleteMembers::class, $fact->projection[0]->missing[0]);
        self::assertSame($view->declarations()[0], $fact->projection[0]->missing[0]->table);
    }

    public function testMissingIsNeverEmpty(): void
    {
        $this->expectExceptionMessage('An open star names at least one missing input.');

        new OpenStar([]);
    }
}

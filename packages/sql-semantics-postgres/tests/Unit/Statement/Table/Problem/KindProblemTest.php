<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(KindProblem::class)]
#[Medium]
final class KindProblemTest extends TestCase
{
    public function testMessageNamesTheRelation(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('DROP TABLE v', [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')]);
        $problem = $statement->facts->diagnostics[0];
        self::assertInstanceOf(KindProblem::class, $problem);
        self::assertSame('"v" is not a table', $problem->message());
    }

    public function testMessageNamesTheAction(): void
    {
        self::assertSame('ALTER action ADD COLUMN cannot be performed on relation "s"', (new KindProblem(KindRule::AlterAction, new Name('s'), AlterAction::AddColumn))->message());
    }

    public function testMessageWithoutRelation(): void
    {
        self::assertSame('unique constraints are not supported on foreign tables', (new KindProblem(KindRule::ForeignUnique, null))->message());
    }
}

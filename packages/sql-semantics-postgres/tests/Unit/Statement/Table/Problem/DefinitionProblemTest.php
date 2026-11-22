<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem::class)]
#[Medium]
final class DefinitionProblemTest extends TestCase
{
    public function testMessageNamesTheSubject(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TABLE t (a int, a int)', []);
        $n1 = $statement->facts->diagnostics[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem::class, $n1);
        self::assertSame('column "a" specified more than once', $n1->message());
    }

    public function testMessageNamesTheSubjectAndItsRelation(): void
    {
        $problem = new \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::IdentityNullable, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'));
        self::assertSame('column "a" of relation "t" must be declared NOT NULL before identity can be added', $problem->message());
    }
}

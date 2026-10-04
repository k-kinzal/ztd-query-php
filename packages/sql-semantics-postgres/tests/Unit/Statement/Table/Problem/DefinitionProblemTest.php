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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, a int)', []);
        $n1 = $statement->facts->diagnostics[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem::class, $n1);
        self::assertSame('column "a" specified more than once', $n1->message());
    }
}

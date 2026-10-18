<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\EvaluationInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EvaluationInput::class)]
#[Small]
final class EvaluationInputTest extends TestCase
{
    public function testErrorPreservesTheWildcardAndEarlierDatabaseDependency(): void
    {
        $session = (new Instance())->connect();
        $reader = new EvaluationInput();

        self::assertSame(1096, $reader->error(new EvaluationWildcard(), $session)->getCode());
        self::assertSame("Unknown table 'd.t'", $reader->error(new EvaluationWildcard(new QualifiedName(new Name('t'), new Name('d'))), $session)->getMessage());
        self::assertSame([[1046, 'No database selected']], $reader->error(new EvaluationWildcard(requiresDatabase: true), $session)->following);
        $session->variables->database = 'd';
        self::assertSame(1096, $reader->error(new EvaluationWildcard(requiresDatabase: true), $session)->getCode());
    }
    public function testDeferredResolvesTheCompleteInstructionWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
        $instruction = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation($instruction);
        self::assertSame(1241, (new EvaluationInput())->deferred($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
        self::assertSame([], $session->diagnostics->conditions);
    }
    public function testBeforeUsesTheFirstCompletedDependency(): void
    {
        $session = (new Instance())->connect();
        $reader = new EvaluationInput();
        self::assertSame(1046, $reader->before('DO t.*,f(),(SELECT a FROM t)', 100, $session)?->getCode());
        self::assertCount(2, $session->diagnostics->conditions);
        self::assertSame(1046, $reader->before('DO t.*,(SELECT a FROM t),f()', 100, $session)?->getCode());
        self::assertCount(1, $session->diagnostics->conditions);
        self::assertNull($reader->before('DO t.*,(SELECT a FROM t)', 10, $session));
        self::assertNull($reader->before('SELECT a FROM t', 100, $session));
    }
}

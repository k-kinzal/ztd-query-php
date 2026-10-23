<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Preparation\JsonNulls;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Node;

#[CoversClass(JsonNulls::class)]
#[Small]
final class JsonNullsTest extends TestCase
{
    public function testCheckRejectsTheCurrentDocumentFromSemanticOperands(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET @document='broken'");
        $operation = $session->analyze("SELECT JSON_EXTRACT(@document, '$') IS NULL");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $compiler = (new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->compiler;
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(3141);

        (new JsonNulls())->check($compiler, (new Walker())->find($operation->statement, Node::class));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Preparation\Escapes;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Node;

#[CoversClass(Escapes::class)]
#[Small]
final class EscapesTest extends TestCase
{
    public function testCheckRejectsCorrelatedDefaultsFromSemanticOperands(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a VARCHAR(5) DEFAULT NULL)');
        $operation = $session->analyze("SELECT 'a' LIKE 'a' ESCAPE (SELECT DEFAULT(a)) FROM t");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $compiler = (new Planner($operation, $operation->statementInformation(), $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->compiler;
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(1210);

        (new Escapes())->check($compiler, (new Walker())->find($operation, Node::class));
    }
    public function testCorrelatedAllowsDefaultsOfLocalSubqueryColumns(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a VARCHAR(5) DEFAULT NULL)');
        $operation = $session->analyze("SELECT 'a' LIKE 'a' ESCAPE (SELECT DEFAULT(a) FROM t LIMIT 1)");
        $defaults = (new Walker())->find($operation, \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like::class);

        self::assertCount(1, $defaults);
        self::assertNotNull($defaults[0]->escape);
        self::assertFalse((new Escapes())->correlated($defaults[0]->escape, $operation->statementInformation()));
    }
}

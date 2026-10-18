<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Session\Parse\Entry\ExplainInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExplainInput::class)]
#[Small]
final class ExplainInputTest extends TestCase
{
    public function testErrorPreservesEachOptionRefusal(): void
    {
        $input = new ExplainInput();

        self::assertSame(1235, $input->error(new RefusedConnectionExplain(true))->getCode());
        self::assertSame(6009, $input->error(new RefusedConnectionExplain(false))->getCode());
        self::assertSame('EXPLAIN ANALYZE does not support the INTO clause.', $input->error(new RefusedExplainInto(analyze: true))->getMessage());
        self::assertSame(6006, $input->error(new RefusedExplainInto())->getCode());
        self::assertSame(6007, $input->error(new RefusedExplainInto(new Name('traditional')))->getCode());
    }

    public function testErrorUsesTheNativeMessageByteLimitForTheRetainedTarget(): void
    {
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name(str_repeat('a', 101))));
        $error = (new ExplainInput())->error($problem);
        self::assertSame(3061, $error->getCode());
        self::assertSame("User variable name '" . str_repeat('a', 100) . "' is illegal", $error->getMessage());
    }
    public function testTargetsKeepsTheSameNamespaceAcrossDatabaseCreation(): void
    {
        $target = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('x'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')), new Name('y'));
        $targets = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([$target], [$table]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets($targets, database: new Name('d'));
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['other']))->connect(database: 'other');
        $input = new ExplainInput();
        self::assertSame(1049, $input->targets($problem, $session)->getCode());
        $session->query('CREATE DATABASE d');
        self::assertSame(1109, $input->targets($problem, $session)->getCode());
        self::assertSame('other', $session->variables->database);
        self::assertSame($targets, $problem->targets);
    }
}

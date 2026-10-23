<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Json;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Json\AttributeInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;

#[CoversClass(AttributeInput::class)]
#[Medium]
final class AttributeInputTest extends TestCase
{
    public function testErrorFormatsAnOperandFailureBeforeTableLookup(): void
    {
        $session = (new Instance())->connect();
        $session->run("CREATE INDEX i ON missing(c) ENGINE_ATTRIBUTE 'text'");
        self::assertSame([['Error', 3980, 'Invalid json attribute, error: "Invalid value." at pos 1: \'ext\'']], $session->diagnostics->conditions);
    }

    public function testErrorPreservesTheDepthConditionAndBoundsTheAttributeDiagnostic(): void
    {
        $problem = new RefusedJsonAttribute(new Text(str_repeat('[', 513) . '0' . str_repeat(']', 513)), 'Terminate parsing due to Handler error.', 101, true);
        $error = (new AttributeInput())->error($problem);
        self::assertSame(3157, $error->getCode());
        self::assertSame('22032', $error->sqlState());
        self::assertSame(3980, $error->following[0][0]);
        self::assertSame(511, strlen($error->following[0][1]));
    }

    public function testValidAttributesDoNotRequireConsumerReinterpretation(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->semantics()->analyze("CREATE TABLE t(a INT SECONDARY_ENGINE_ATTRIBUTE '', b INT SECONDARY_ENGINE_ATTRIBUTE 'null', KEY(a) SECONDARY_ENGINE_ATTRIBUTE '[]') SECONDARY_ENGINE_ATTRIBUTE '1'")->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $statement);
        $session->query('CREATE DATABASE placement');
        $session->query('USE placement');
        $session->text = 'unrelated invalid input';
        $result = (new \Tests\Fake\MemoryClient())->observe($session, $statement);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame('unrelated invalid input', $session->text);
    }
}

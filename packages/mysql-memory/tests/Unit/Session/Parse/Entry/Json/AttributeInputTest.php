<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Json;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\Entry\Json\AttributeInput;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;
use Tests\Support\Bindings;
use Tests\Support\Embedding;

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
        $statement = $session->semantics()->analyze("CREATE TABLE t(a INT SECONDARY_ENGINE_ATTRIBUTE '', b INT SECONDARY_ENGINE_ATTRIBUTE 'null', KEY(a) SECONDARY_ENGINE_ATTRIBUTE '[]') SECONDARY_ENGINE_ATTRIBUTE '1'");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        self::assertInstanceOf(CreateTable::class, $statement);
        $session->query('CREATE DATABASE placement');
        $session->query('USE placement');
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($statement);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $result = $result1;
        self::assertNotInstanceOf(SqlError::class, $result);
        self::assertSame('unrelated invalid input', $session->text);
    }
}

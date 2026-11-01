<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Json\ValueCall;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(ValueCall::class)]
#[Small]
final class ValueCallTest extends TestCase
{
    public function testDomainIsTheReturningType(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new ValueCall(new Constant(Domain::null(), null), new JsonPath([]), $domain, [JsonResponseKind::Null, null], [JsonResponseKind::Null, null]))->domain());
    }

    public function testEvaluateAnswersTheResponses(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_VALUE('{\"a\":\"abc\"}', '$.a' RETURNING SIGNED), JSON_VALUE('{\"a\":\"abc\"}', '$.a' RETURNING SIGNED DEFAULT 7 ON ERROR), JSON_VALUE('{}', '$.a' DEFAULT 'zz' ON EMPTY), JSON_VALUE('[1,2]', '$[*]'), JSON_VALUE('{\"a\":[1]}', '$.a'), JSON_VALUE('{\"a\":1.5}', '$.a' RETURNING SIGNED)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '7', 'zz', null, '[1]', '2']], $result->rows);
    }

    public function testEvaluateWarnsOfADocumentThatIsNoJsonText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT JSON_VALUE('{', '$.a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame(1, $result->warnings);
    }

    public function testEvaluateRaisesTheErrorOfErrorOnEmpty(): void
    {
        $this->expectExceptionMessage("No value was found by 'json_value' on the specified path.");

        (new Instance())->connect()->query("SELECT JSON_VALUE('{}', '$.a' ERROR ON EMPTY)");
    }

    public function testEvaluateRaisesTheErrorOfErrorOnErrorForManyValues(): void
    {
        $this->expectExceptionMessage("More than one value was found by 'json_value' on the specified path.");

        (new Instance())->connect()->query("SELECT JSON_VALUE('[1,2]', '$[*]' ERROR ON ERROR)");
    }

    public function testEvaluateRaisesTheErrorOfErrorOnErrorForAValueOutOfRange(): void
    {
        $this->expectExceptionMessage("UNSIGNED value is out of range in 'json_value'");

        (new Instance())->connect()->query("SELECT JSON_VALUE('{\"a\":-3}', '$.a' RETURNING UNSIGNED ERROR ON ERROR)");
    }

    public function testRespondAnswersTheDefaultValue(): void
    {
        $session = (new Instance())->connect();
        $call = new ValueCall(new Constant(Domain::null(), null), new JsonPath([]), Domain::integer(), [JsonResponseKind::Null, null], [JsonResponseKind::Null, null]);

        self::assertSame(['x', null], [$call->respond([JsonResponseKind::Default, new Constant(Domain::string(1, Collation::binary()), 'x')], new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))), $call->respond([JsonResponseKind::Null, null], new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))]);
    }
}

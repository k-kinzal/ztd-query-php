<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Index;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Index\Elements;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Elements::class)]
#[Small]
final class ElementsTest extends TestCase
{
    public function testConvertKeepsRoundingNotesAtTheIndexAndCurrentRow(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $context->row = 3;
        $value = (new Elements(Domain::decimal(6, 2), 'k'))->convert(JsonNode::parse('1.234'), $context);
        self::assertSame('1.23', $value->text());
        self::assertSame([['Note', 3751, "Data truncated for functional index 'k' at row 3"]], $context->diagnostics->conditions);
    }

    public function testSourceRetainsJsonStringCharactersForTextEntries(): void
    {
        $elements = new Elements(Domain::string(5, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_bin')), 'k');
        self::assertSame('x ', $elements->source(JsonNode::parse('"x "'))[0]);
    }

    public function testFailureRetainsTheRangeConditionAfterAConversionError(): void
    {
        $failure = (new Elements(Domain::integer(), 'k'))->failure(DataError::OutOfRange->error('internal', 1), 7);
        self::assertSame(ConstraintError::FunctionalRange, $failure->error);
        self::assertSame([[3752, "Value is out of range for functional index 'k' at row 7"]], $failure->following);
    }

    public function testFailureDoesNotTranslateAnUnrelatedSqlFailure(): void
    {
        $error = DataError::DuplicateEntry->error('1', 't.k');
        $this->expectExceptionObject($error);
        (new Elements(Domain::integer(), 'k'))->failure($error, 1);
    }
}

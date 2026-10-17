<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Index;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Index\ArrayConversion;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ArrayConversion::class)]
#[Small]
final class ArrayConversionTest extends TestCase
{
    public function testDomainSeparatesTheDocumentFromItsEntryType(): void
    {
        $entries = new ArrayConversion(new Constant(Domain::null(), null), Domain::integer(), 'k');
        self::assertSame(Kind::Json, $entries->domain()->kind);
        self::assertSame(Kind::Integer, $entries->elementDomain->kind);
    }

    public function testEvaluateConvertsSortsAndDeduplicatesEntries(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new Constant(new Domain(Kind::Json, Field::Json), '[3, 1, "2", 1]');
        self::assertSame('[1, 2, 3]', (new ArrayConversion($document, Domain::integer(), 'k'))->evaluate($frame));
        self::assertNull((new ArrayConversion(new Constant(Domain::null(), null), Domain::integer(), 'k'))->evaluate($frame));
    }

    public function testAtRestoresTheCallerRowAfterEntryConversion(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);
        $document = new Constant(new Domain(Kind::Json, Field::Json), '[1.234]');
        $entries = new ArrayConversion($document, Domain::decimal(6, 2), 'k');
        $entries->at(new Frame($context), 7);
        self::assertSame(1, $context->row);
        self::assertSame([['Note', 3751, "Data truncated for functional index 'k' at row 7"]], $context->diagnostics->conditions);
    }

    public function testEvaluateRejectsNestedDocumentsBeforeKeyComparison(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new Constant(new Domain(Kind::Json, Field::Json), '[[1]]');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3908);
        (new ArrayConversion($document, Domain::integer(), 'k'))->evaluate($frame);
    }
}

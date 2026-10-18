<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Resolver;

use Deriver\Deriver\Resolver\ExpressionValidation;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressionValidation::class)]
#[Small]
final class ExpressionValidationTest extends TestCase
{
    public function testEnterNodeRejectsLiteralUnpackButDefersUnknownOperandTypes(): void
    {
        $errors = new Collecting();
        $validation = new ExpressionValidation($errors);
        $validation->enterNode(new ArrayItem(new Variable('items'), unpack: true));
        $before = $errors->getErrors();
        $validation->enterNode(new ArrayItem(new Int_(7), unpack: true));
        self::assertSame([], $before);
        self::assertCount(1, $errors->getErrors());
        self::assertSame('Only arrays and Traversables can be unpacked.', $errors->getErrors()[0]->getRawMessage());
    }
}

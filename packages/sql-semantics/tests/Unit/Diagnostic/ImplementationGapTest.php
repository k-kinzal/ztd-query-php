<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;

#[CoversClass(ImplementationGap::class)]
#[Small]
final class ImplementationGapTest extends TestCase
{
    public function testProductionNamesTheUnclaimedSignature(): void
    {
        $form = new Form(new Node('where_opt', 1, []), 'where_opt: WHERE expr');

        $gap = ImplementationGap::production($form);

        self::assertSame('No semantic rule is implemented for: where_opt: WHERE expr', $gap->getMessage());
    }

    public function testRuleNamesTheObligation(): void
    {
        $gap = ImplementationGap::rule('rendering of window frames');

        self::assertSame('No semantic rule is implemented for: rendering of window frames', $gap->getMessage());
    }
}

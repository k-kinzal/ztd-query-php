<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WriteMisuse::class)]
#[Small]
final class WriteMisuseTest extends TestCase
{
    public function testMessageAnswersTheRuleMessage(): void
    {
        self::assertSame('Incorrect usage of UPDATE and ORDER BY', (new WriteMisuse(WriteRule::OrderedMultipleUpdate))->message());
    }

    public function testMessageDescribesTheRuleOfANamedTarget(): void
    {
        $misuse = new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d'));

        self::assertSame('d', $misuse->table?->value);
        self::assertSame('The target table is a derived table, a table function or a common table expression, which is not updatable', $misuse->message());
    }
}

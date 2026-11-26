<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoLanguage;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(DoLanguage::class)]
#[Small]
final class DoLanguageTest extends TestCase
{
    public function testNameAnswersTheWordOrTheString(): void
    {
        self::assertSame(['plpgsql', 'PL'], [(new DoLanguage(new Word(new Name('plpgsql'))))->name(), (new DoLanguage(new StringConstant('PL')))->name()]);
    }

    public function testRenderWritesTheClause(): void
    {
        self::assertSame("DO 'x' LANGUAGE \"PL\"", (new Inspection())->inspect("DO 'x' LANGUAGE \"PL\"")->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Choices::class)]
#[Medium]
final class ChoicesTest extends TestCase
{
    public function testKnownDistinguishesTheFiniteOptionDomains(): void
    {
        $choices = new Choices();
        self::assertTrue($choices->known(null, false));
        self::assertTrue($choices->known(new \SqlSemantics\Statement\Identifier\Name('inStAnT'), false));
        self::assertTrue($choices->known(new \SqlSemantics\Statement\Identifier\Name('shared'), true));
        self::assertFalse($choices->known(new \SqlSemantics\Statement\Identifier\Name('SHARED'), false));
        self::assertFalse($choices->known(new \SqlSemantics\Statement\Identifier\Name('INPLACE'), true));
    }

    public function testRefusalKeepsReleaseSpecificNamesAtTheInputBoundary(): void
    {
        $choices = new Choices();
        $old = \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $new = \SqlSemantics\Contract\GrammarRelease::MySql847;
        self::assertNull($choices->refusal(null, true, $new));
        self::assertNull($choices->refusal(new \SqlSemantics\Statement\Identifier\Name('DEFAULT'), false, $old));
        self::assertNotNull($choices->refusal(new \SqlSemantics\Statement\Identifier\Name('DEFAULT'), false, $new));
        self::assertNotNull($choices->refusal(new \SqlSemantics\Statement\Identifier\Name('INSTANT'), false, $old));
        self::assertNull($choices->refusal(new \SqlSemantics\Statement\Identifier\Name('INSTANT'), false, $new));
    }

    public function testAlgorithmChecksTheAlgorithm(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('ALTER TABLE t ALGORITHM = COPY, ALGORITHM = INSTANT')->diagnostics());
    }

    public function testLockChecksTheLock(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('ALTER TABLE t LOCK = none, LOCK = EXCLUSIVE')->diagnostics());
    }

    public function testRefusalAcceptsDefaultAsANameIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ALGORITHM = `DEFAULT`, LOCK = `DEFAULT`')->diagnostics()));
    }

    public function testRefusalIgnoresTheKeywordDefault(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ALGORITHM = DEFAULT, LOCK = DEFAULT')->diagnostics()));
    }
}

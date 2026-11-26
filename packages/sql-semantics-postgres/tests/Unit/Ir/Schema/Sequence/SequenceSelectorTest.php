<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SequenceSelector::class)]
final class SequenceSelectorTest extends TestCase
{
    public function testCheckProfileRejectsAnOccupiedStockCoordinate(): void
    {
        $target = new SequenceSelector(new Identifier('pg_class'), new Identifier('pg_catalog'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A stock non-sequence relation cannot be a sequence target.');

        $target->checkProfile(GrammarRelease::PostgreSql172);
    }

    public function testQuotedPreservesTheUnqualifiedAbsenceCoordinate(): void
    {
        $target = new SequenceSelector(new Identifier('Missing."棚"'), null, SequencePresence::MissingSequence);

        self::assertSame('"Missing.""棚"""', $target->quoted());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas;

#[CoversClass(SequencesInSchemas::class)]
final class SequencesInSchemasTest extends TestCase
{
    public function testNumberedTemporarySchemaIsRejected(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence privileges cannot use a numbered temporary schema.');
        new SequencesInSchemas([new Identifier('pg_temp_21')]);
    }

    public function testToStringQuotesEachSchemaIndependently(): void
    {
        $target = new SequencesInSchemas([new Identifier('a'), new Identifier('Archive.Events')]);
        self::assertSame('ALL SEQUENCES IN SCHEMA "a", "Archive.Events"', $target->toString());
    }
}

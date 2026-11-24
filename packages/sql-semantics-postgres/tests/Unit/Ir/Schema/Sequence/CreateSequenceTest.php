<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceParameters;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePersistence;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateSequence::class)]
final class CreateSequenceTest extends TestCase
{
    public function testToStringKeepsTheGeneratorStartAndInitialValueSeparate(): void
    {
        $name = new SequenceName(new Identifier('app'), new Identifier('ticket'));
        $parameters = new SequenceParameters(IntegerStorage::Small, start: new WideIntegerLiteral('3'), initial: new WideIntegerLiteral('8'));
        $value = new CreateSequence($name, $parameters, SequencePersistence::Unlogged, true);

        self::assertSame(
            'CREATE UNLOGGED SEQUENCE IF NOT EXISTS "app"."ticket" AS SMALLINT INCREMENT BY 1 MINVALUE 1 MAXVALUE 32767'
            . ' START WITH 3 RESTART WITH 8 CACHE 1 NO CYCLE',
            $value->toString(),
        );
    }

    public function testProfileIdKeepsTheRelease(): void
    {
        $name = new SequenceName(new Identifier('pg_temp'), new Identifier('ticket'));
        $value = new CreateSequence($name, persistence: SequencePersistence::Temporary, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testRejectsPersistentStorageInTheTemporaryNamespace(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence namespace must agree with its temporary storage.');

        new CreateSequence(new SequenceName(new Identifier('pg_temp'), new Identifier('ticket')));
    }
}

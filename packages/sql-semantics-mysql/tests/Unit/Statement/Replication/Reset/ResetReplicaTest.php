<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ResetReplica::class)]
#[Medium]
final class ResetReplicaTest extends TestCase
{
    public function testDeriveTargetRejectsTheVocabularyOfAnotherRelease(): void
    {
        $this->expectExceptionMessage('RESET REPLICA is written in the vocabulary of the release.');

        new InspectedStatement((new Inspection('mysql-5.7.44'))->context([]), new Reset([new ResetReplica(Terminology::Current)]));
    }

    public function testRenderWritesTheLegacySpellingWithChannel(): void
    {
        self::assertSame("RESET SLAVE ALL FOR CHANNEL 'c'", (new Inspection('mysql-5.7.44'))->inspect("reset slave all for channel 'c'")->toString());
    }
}

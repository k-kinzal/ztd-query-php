<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationAction;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(AlterSequenceAuthorization::class)]
final class AlterSequenceAuthorizationTest extends TestCase
{
    public function testToStringKeepsTheDetachedMixedOrder(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('app'));
        $first = new SequenceAuthorizationAction(SequenceLogging::Logged);
        $owner = new SequenceAuthorizationAction(new SequencePrincipal(SchemaSessionRole::Session));
        $actions = [&$first, $owner, new SequenceAuthorizationAction(SequenceLogging::Unlogged)];
        $value = new AlterSequenceAuthorization($source, $actions);
        $first = new SequenceAuthorizationAction(SequenceLogging::Unlogged);

        self::assertSame('ALTER SEQUENCE "app"."ticket" SET LOGGED, OWNER TO SESSION_USER, SET UNLOGGED', $value->toString());
    }

    public function testProfileIdRetainsTheExactRelease(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('app'));
        $owner = new SequenceAuthorizationAction(new SequencePrincipal(SchemaSessionRole::Effective));
        $value = new AlterSequenceAuthorization($source, [$owner], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testRejectsLoggingWithoutAnAuthorizationAction(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('app'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A present sequence authorization requires an owning role assignment.');

        new AlterSequenceAuthorization($source, [new SequenceAuthorizationAction(SequenceLogging::Logged)]);
    }
}

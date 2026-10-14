<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Grantee::class)]
#[Small]
final class GranteeTest extends TestCase
{
    public function testFromRetainsOnlyTheAccountAndAuthentication(): void
    {
        $user = new AccountName(new Name('app'));
        $authentication = new Identification(null, Credential::Password, new Text('pw'));
        $grantee = Grantee::from(new UserSpecification($user, $authentication));

        self::assertSame(['user' => $user, 'identification' => $authentication], get_object_vars($grantee));
        self::assertSame($grantee, Grantee::from($grantee));
    }

    public function testFromRejectsPasswordManagement(): void
    {
        $this->expectExceptionMessage('A GRANT account is a named account with at most one authentication method.');
        Grantee::from(new UserSpecification(new AccountName(new Name('app')), discardOld: true));
    }

    public function testFromRejectsAnUnrelatedNode(): void
    {
        $this->expectExceptionMessage('A GRANT account is a named account with at most one authentication method.');
        Grantee::from(new Text('app'));
    }

    public function testValueRejectsRandomPasswordAuthentication(): void
    {
        $this->expectExceptionMessage('GRANT authentication does not generate a random password.');
        new Grantee(new AccountName(new Name('app')), new Identification(null, Credential::RandomPassword));
    }

    public function testRenderWritesTheRetainedFields(): void
    {
        $grantee = new Grantee(new AccountName(new Name('app')), new Identification(null, Credential::Password, new Text('pw')));
        $out = new Output(new Codec(GrammarRelease::MySql5744));
        $grantee->render($out);

        self::assertSame("app IDENTIFIED BY 'pw'", (new Lexical())->join($out->pieces()));
    }
}

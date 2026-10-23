<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\GroupCredentials;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(GroupCredentials::class)]
#[Small]
final class GroupCredentialsTest extends TestCase
{
    public function testProblemChecksExplicitCredentialsInOrder(): void
    {
        $rule = new GroupCredentials();
        self::assertNull($rule->problem([]));
        self::assertSame(ReplicationError::GroupUserMissing, $rule->problem([new CredentialOption(Credential::Password, new Text('p'))])?->error);
        self::assertSame(ReplicationError::GroupUserEmpty, $rule->problem([new CredentialOption(Credential::User, new Text('u')), new CredentialOption(Credential::User, new Text('')), new CredentialOption(Credential::Password, new Text('p'))])?->error);
        self::assertSame(ReplicationError::GroupPasswordTooLong, $rule->problem([new CredentialOption(Credential::Password, new Text(str_repeat('p', 33)))])?->error);
        self::assertNull($rule->problem([new CredentialOption(Credential::User, new Text('')), new CredentialOption(Credential::User, new Text('u')), new CredentialOption(Credential::Password, new Text('p'))]));
    }
}

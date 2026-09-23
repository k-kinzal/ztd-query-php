<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(UserMappingIdentity::class)]
#[Medium]
final class UserMappingIdentityTest extends TestCase
{
    public function testIdentityPairsANamedUserWithItsForeignServer(): void
    {
        $user = new NamedRole('Alice');
        $target = new UserMappingIdentity($user, 'remote');
        self::assertSame($user, $target->user);
        self::assertSame('remote', $target->server);
    }

    public function testIdentityKeepsPublicFallbackSeparateFromANamedRole(): void
    {
        $target = new UserMappingIdentity(MappingPrincipal::PublicDefault, 'remote');
        self::assertSame(MappingPrincipal::PublicDefault, $target->user);
        self::assertSame('remote', $target->server);
    }

    public function testIdentityRequiresANonemptyServer(): void
    {
        $this->expectException(InvalidStructure::class);
        new UserMappingIdentity(MappingPrincipal::CurrentUser, '');
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(AddForeignKeys::class)]
#[Small]
final class AddForeignKeysTest extends TestCase
{
    public function testToStringReconstructsAClosedNativeAdditionWithDetachedOperands(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $key = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'PRIMARY', IndexRole::Primary, 'id');
        $addition = NativeAdditionFixture::addition('Link', false, 'Link');
        $root = new AddForeignKeys($owner, [&$addition], [$key], []);
        $addition = NativeAdditionFixture::addition('Other', false, 'Other');

        self::assertSame('ALTER TABLE `app`.`child` ADD CONSTRAINT `Link` FOREIGN KEY (`owner_id`) REFERENCES `app`.`parent` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE', $root->toString());
    }

    public function testProfileIdNamesThePinnedNativeMode(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $key = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'PRIMARY', IndexRole::Primary, 'id');
        $root = new AddForeignKeys($owner, [NativeAdditionFixture::addition('Link', false, 'Link')], [$key], []);

        self::assertStringStartsWith('mysql-9.1.0/', $root->profileId());
    }
}

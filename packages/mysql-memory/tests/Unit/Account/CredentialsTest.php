<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Credentials;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Credentials::class)]
#[Small]
final class CredentialsTest extends TestCase
{
    public function testPluginAnswersALoadedPluginInLowerCase(): void
    {
        self::assertSame('sha256_password', (new Credentials())->plugin('SHA256_PASSWORD'));
    }

    public function testPluginRefusesThePluginsThatAreNotLoaded(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1524);
        $this->expectExceptionMessage("Plugin 'mysql_native_password' is not loaded");

        (new Credentials())->plugin('mysql_native_password');
    }

    public function testHashAnswersTheFormOfEachPlugin(): void
    {
        $credentials = new Credentials();

        self::assertSame([70, '$A$005$', 67, '$5$', ''], [strlen($credentials->hash('caching_sha2_password', 'x')), substr($credentials->hash('caching_sha2_password', 'x'), 0, 7), strlen($credentials->hash('sha256_password', 'x')), substr($credentials->hash('sha256_password', 'x'), 0, 3), $credentials->hash('caching_sha2_password', '')]);
    }

    public function testCheckAcceptsAnEmptyStringAndOneOfTheForm(): void
    {
        $this->expectNotToPerformAssertions();

        (new Credentials())->check('caching_sha2_password', '');
        (new Credentials())->check('caching_sha2_password', '$A$005$' . str_repeat('b', 63));
        (new Credentials())->check('sha256_password', '$5$' . str_repeat('b', 20) . '$' . str_repeat('a', 43));
    }

    public function testCheckRefusesAStringOfAnotherForm(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1827);
        $this->expectExceptionMessage("The password hash doesn't have the expected format.");

        (new Credentials())->check('caching_sha2_password', 'abc');
    }

    public function testGenerateAnswersTwentyCharacters(): void
    {
        self::assertSame(20, strlen((new Credentials())->generate()));
    }
}

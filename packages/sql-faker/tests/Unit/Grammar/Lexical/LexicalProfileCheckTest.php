<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalProfileCheck;

#[CoversClass(LexicalProfileCheck::class)]
final class LexicalProfileCheckTest extends TestCase
{
    /**
     * @param array<string, mixed> $profile
     */
    #[DataProvider('providerInvalidIdentities')]
    public function testAssertCompatibleRejectsAnInvalidReleaseIdentity(array $profile): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lexical profile identity: mysql mysql-8.4.7');

        (new LexicalProfileCheck())->assertCompatible(
            $profile,
            'mysql',
            'mysql-8.4.7',
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function providerInvalidIdentities(): iterable
    {
        yield 'other version' => [['dialect' => 'mysql', 'version' => 'mysql-8.0.44']];
        yield 'other dialect' => [['dialect' => 'postgresql', 'version' => 'mysql-8.4.7']];
        yield 'missing dialect' => [['version' => 'mysql-8.4.7']];
        yield 'missing version' => [['dialect' => 'mysql']];
    }

    public function testAssertCompatibleRequiresOnlyTheDeclaredIdentity(): void
    {
        (new LexicalProfileCheck())->assertCompatible(['dialect' => 'mysql', 'version' => 'mysql-8.4.7'], 'mysql', 'mysql-8.4.7');
        $this->expectNotToPerformAssertions();
    }
}

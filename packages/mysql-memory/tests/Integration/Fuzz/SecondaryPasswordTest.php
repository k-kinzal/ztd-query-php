<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class SecondaryPasswordTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChanges(): iterable
    {
        $setup = "DROP USER IF EXISTS dual_test; CREATE USER dual_test IDENTIFIED BY 'first'; ";
        if (str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'legacy rejects retention' => [$setup . "ALTER USER dual_test IDENTIFIED BY 'second' RETAIN CURRENT PASSWORD"];

            return;
        }
        $primary = "(SELECT authentication_string FROM mysql.user WHERE User='dual_test' AND Host='%')";
        $secondary = "JSON_UNQUOTE(JSON_EXTRACT(User_attributes,'$.additional_password'))";
        $inspect = "SELECT {$secondary} IS NULL AS absent, {$secondary}=@previous AS retained, authentication_string=@previous AS original FROM mysql.user WHERE User='dual_test' AND Host='%'; ";
        $setup .= "SET @previous={$primary}; ";
        $retain = "ALTER USER dual_test IDENTIFIED BY 'second' RETAIN CURRENT PASSWORD; ";
        yield 'retain previous primary' => [$setup . $retain . $inspect];
        yield 'set retains previous primary' => [$setup . "SET PASSWORD FOR dual_test = 'second' RETAIN CURRENT PASSWORD; " . $inspect];
        yield 'retention replaces older secondary' => [$setup . $retain . "SET @previous={$primary}; ALTER USER dual_test IDENTIFIED BY 'third' RETAIN CURRENT PASSWORD; " . $inspect];
        yield 'ordinary change preserves secondary' => [$setup . $retain . "ALTER USER dual_test IDENTIFIED BY 'third'; " . $inspect];
        yield 'empty password removes secondary' => [$setup . $retain . "ALTER USER dual_test IDENTIFIED BY ''; " . $inspect];
        yield 'discard removes secondary' => [$setup . $retain . 'ALTER USER dual_test DISCARD OLD PASSWORD; ' . $inspect];
        yield 'discard without secondary succeeds' => [$setup . 'ALTER USER dual_test DISCARD OLD PASSWORD; ' . $inspect];
        yield 'plugin change discards secondary' => [$setup . $retain . "ALTER USER dual_test IDENTIFIED WITH sha256_password BY 'third'; " . $inspect];
        yield 'plugin change cannot retain' => [$setup . "ALTER USER dual_test IDENTIFIED WITH sha256_password BY 'second' RETAIN CURRENT PASSWORD"];
        yield 'empty current cannot be retained' => ["DROP USER IF EXISTS dual_test; CREATE USER dual_test; ALTER USER dual_test IDENTIFIED BY 'second' RETAIN CURRENT PASSWORD"];
        yield 'empty new cannot retain' => [$setup . "ALTER USER dual_test IDENTIFIED BY '' RETAIN CURRENT PASSWORD"];
        yield 'set empty current cannot be retained' => ["DROP USER IF EXISTS dual_test; CREATE USER dual_test; SET PASSWORD FOR dual_test = 'second' RETAIN CURRENT PASSWORD"];
        yield 'set empty new cannot retain' => [$setup . "SET PASSWORD FOR dual_test = '' RETAIN CURRENT PASSWORD"];
    }

    #[DataProvider('providerChanges')]
    public function testDualPasswordStateMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
    }
}

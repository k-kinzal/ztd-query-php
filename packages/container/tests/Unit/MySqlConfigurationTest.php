<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\ContainerInstance;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\Types\Mount;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;
use Testcontainers\Containers\WaitStrategy\WaitStrategy;

#[CoversClass(MySqlConfiguration::class)]
final class MySqlConfigurationTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testApplyConfiguresTheDatabaseStartupContract(): void
    {
        $container = new class ('database:version') extends GenericContainer {
            /**
             * @return array{ports: array<int>, env: array<string, string>, mounts: array<Mount>, timeout: int|null, retries: int, remove: bool, wait: WaitStrategy|null}
             * @throws \Testcontainers\Exceptions\InvalidFormatException
             */
            public function launchOptions(ContainerInstance $instance): array
            {
                return [
                    'ports' => $this->exposedPorts(),
                    'env' => $this->env(),
                    'mounts' => $this->mounts(),
                    'timeout' => $this->startupTimeout(),
                    'retries' => $this->startupConflictRetryAttempts(),
                    'remove' => $this->autoRemoveOnExit(),
                    'wait' => $this->waitStrategy($instance),
                ];
            }
        };
        (new MySqlConfiguration())->apply($container);
        $options = $container->launchOptions(self::createStub(ContainerInstance::class));
        self::assertTrue($container->reuseMode()->isReuse());
        self::assertSame([3306], $options['ports']);
        self::assertSame(['MYSQL_ROOT_PASSWORD' => 'root', 'MYSQL_ROOT_HOST' => '%', 'MYSQL_DATABASE' => 'test', 'MYSQL_INITDB_SKIP_TZINFO' => '1'], $options['env']);
        self::assertEquals([Mount::fromString('type=tmpfs,destination=/var/lib/mysql')], $options['mounts']);
        self::assertSame(300, $options['timeout']);
        self::assertSame(10, $options['retries']);
        self::assertTrue($options['remove']);
        self::assertEquals((new PDOConnectWaitStrategy())->withDsn((new MySQLDSN())->withDbname('test')->withCharset('utf8mb4'))->withUsername('root')->withPassword('root')->withTimeoutSeconds(120)->withRetryInterval(250000), $options['wait']);
    }
}

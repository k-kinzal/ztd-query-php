<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\PostgreSqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\ContainerInstance;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\Types\Mount;
use Testcontainers\Containers\WaitStrategy\LogMessageWaitStrategy;
use Testcontainers\Containers\WaitStrategy\WaitStrategy;

#[CoversClass(PostgreSqlConfiguration::class)]
final class PostgreSqlConfigurationTest extends TestCase
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
        (new PostgreSqlConfiguration())->apply($container);
        $options = $container->launchOptions(self::createStub(ContainerInstance::class));
        self::assertTrue($container->reuseMode()->isReuse());
        self::assertSame([5432], $options['ports']);
        self::assertSame(['POSTGRES_USER' => 'test', 'POSTGRES_PASSWORD' => 'test', 'POSTGRES_DB' => 'test'], $options['env']);
        self::assertEquals([], $options['mounts']);
        self::assertSame(300, $options['timeout']);
        self::assertSame(10, $options['retries']);
        self::assertTrue($options['remove']);
        self::assertEquals((new LogMessageWaitStrategy())->withPattern('\\[1\\].*database system is ready to accept connections')->withTimeoutSeconds(120), $options['wait']);
    }
}

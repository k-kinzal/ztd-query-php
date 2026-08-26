<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakeSchemaReflector;
use Tests\Fake\FakeSessionFactory;
use ZtdQuery\Config\ZtdConfig;

#[CoversNothing]
final class SessionFactoryTest extends TestCase
{
    public function testCreateAnswersASessionThatIsAlreadyOn(): void
    {
        $session = (new FakeSessionFactory())->create(new FakeConnection([]), ZtdConfig::default());

        self::assertTrue($session->isEnabled());
    }

    public function testCreateReadsEveryTableTheDatabaseAlreadyHas(): void
    {
        $factory = new FakeSessionFactory(new FakeSchemaReflector([
            'users' => 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY)',
        ]));

        $session = $factory->create(new FakeConnection([]), ZtdConfig::default());

        self::assertNotNull($session->tableDefinition('users'));
    }

    public function testCreateAnswersASessionThatHasSimulatedNothingYet(): void
    {
        $factory = new FakeSessionFactory(new FakeSchemaReflector([
            'users' => 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY)',
        ]));

        $session = $factory->create(new FakeConnection([]), ZtdConfig::default());

        self::assertFalse($session->lastInsertId());
    }
}

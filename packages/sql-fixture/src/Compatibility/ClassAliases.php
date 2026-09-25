<?php

declare(strict_types=1);

namespace SqlFixture\Compatibility;

class_alias(\SqlFixture\Provider\FixtureProvider::class, 'SqlFixture\\FixtureProvider');
class_alias(\SqlFixture\Provider\DatabaseFixtureProvider::class, 'SqlFixture\\DatabaseFixtureProvider');
class_alias(\SqlFixture\Provider\FileFixtureProvider::class, 'SqlFixture\\FileFixtureProvider');
class_alias(\SqlFixture\Provider\FixtureGenerator::class, 'SqlFixture\\FixtureGenerator');
class_alias(\SqlFixture\Provider\PlatformFactory::class, 'SqlFixture\\Platform\\PlatformFactory');
class_alias(\SqlFixture\Provider\Exception\DriverDetectionException::class, 'SqlFixture\\Platform\\Exception\\DriverDetectionException');
class_alias(\SqlFixture\Provider\Exception\UnsupportedDriverException::class, 'SqlFixture\\Platform\\Exception\\UnsupportedDriverException');
class_alias(MySqlSchemaParser::class, 'SqlFixture\\Schema\\MySqlSchemaParser');
class_alias(MySqlTypeMapper::class, 'SqlFixture\\TypeMapper\\MySqlTypeMapper');
class_alias(\SqlFixture\Fixture\Exception\InvalidOverrideException::class, 'SqlFixture\\InvalidOverrideException');

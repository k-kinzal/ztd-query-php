<?php

declare(strict_types=1);

namespace SqlFaker\Compatibility;

class_alias(\SqlFaker\MySql\MySqlProvider::class, 'SqlFaker\\MySqlProvider');
class_alias(\SqlFaker\PostgreSql\PostgreSqlProvider::class, 'SqlFaker\\PostgreSqlProvider');
class_alias(\SqlFaker\Sqlite\SqliteProvider::class, 'SqlFaker\\SqliteProvider');
class_alias(SqlGeneratorFactory::class, 'SqlFaker\\Provider\\SqlGeneratorFactory');

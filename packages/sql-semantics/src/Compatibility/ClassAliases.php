<?php

declare(strict_types=1);

namespace SqlSemantics\Compatibility;

class_alias(\SqlSemantics\Core\SemanticException::class, 'SqlSemantics\\SemanticException');
class_alias(\SqlSemantics\Core\SchemaBuilder::class, 'SqlSemantics\\SchemaBuilder');
class_alias(\SqlSemantics\Core\Schema::class, 'SqlSemantics\\Schema');
class_alias(\SqlSemantics\Core\Binder::class, 'SqlSemantics\\Binder');
class_alias(\SqlSemantics\Facade\Dialect::class, 'SqlSemantics\\Dialect');
class_alias(\SqlSemantics\Core\Schema\ColumnDefinition::class, 'SqlSemantics\\Schema\\ColumnDefinition');
class_alias(\SqlSemantics\Core\Schema\TableDefinition::class, 'SqlSemantics\\Schema\\TableDefinition');
class_alias(\SqlSemantics\Core\Schema\ConstraintKind::class, 'SqlSemantics\\Schema\\ConstraintKind');
class_alias(\SqlSemantics\Core\Schema\TableConstraint::class, 'SqlSemantics\\Schema\\TableConstraint');
class_alias(\SqlSemantics\Core\Type\Nullability::class, 'SqlSemantics\\Type\\Nullability');
class_alias(\SqlSemantics\Core\Type\TypeDescriptor::class, 'SqlSemantics\\Type\\TypeDescriptor');
class_alias(\SqlSemantics\Core\Model\Expression::class, 'SqlSemantics\\Model\\Expression');
class_alias(\SqlSemantics\Core\Model\Join::class, 'SqlSemantics\\Model\\Join');
class_alias(\SqlSemantics\Core\Model\ExpressionKind::class, 'SqlSemantics\\Model\\ExpressionKind');
class_alias(\SqlSemantics\Core\Model\BoundSelect::class, 'SqlSemantics\\Model\\BoundSelect');
class_alias(\SqlSemantics\Core\Model\TableUse::class, 'SqlSemantics\\Model\\TableUse');
class_alias(\SqlSemantics\Core\Model\ColumnBinding::class, 'SqlSemantics\\Model\\ColumnBinding');
class_alias(\SqlSemantics\Core\Model\Ordering::class, 'SqlSemantics\\Model\\Ordering');
class_alias(\SqlSemantics\Core\Model\OutputColumn::class, 'SqlSemantics\\Model\\OutputColumn');
class_alias(\SqlSemantics\Core\Model\JoinKind::class, 'SqlSemantics\\Model\\JoinKind');

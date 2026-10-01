# Reference resolution

`Semantics::analyze()` is the single analysis entry point. The optional second
argument is a closed catalog of `Semantic\Schema\Table` declarations. Omitting it
means that declarations were not supplied; passing `[]` means that no tables exist
in the supplied catalog. There is no separate `Binder` API.

```php
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Schema\Column;
use SqlSemantics\Semantic\Schema\Table;

$dialect = Dialect::Sqlite;
$foo = new Column(new Name('foo'), new TypeDescriptor($dialect, 'integer'), Nullability::NotNull);
$bar = new Table($dialect, new QualifiedName(new Name('bar')), $foo);
$semantics = new Semantics($dialect);

$open = $semantics->analyze('SELECT foo FROM bar');
$open->field('foo')->type->name; // 'unknown'
$open->field('foo')->type->reason->value; // 'catalog-not-supplied'

$known = $semantics->analyze('SELECT foo FROM bar', [$bar]);
$known->field('foo')->type->name; // 'integer'
$known->tables[0]->declaration === $bar; // true
$known->field('foo')->expression->binding->column === $foo; // true
```

A table declaration and its occurrence in a query are different values. A self
join has two occurrences sharing one declaration. `ResolvedColumn` identifies the
occurrence and the exact declared column. An outer join changes the nullability of
a reference in the result scope without changing the column declaration or its
nullability in the join's match condition.

Column resolution produces one of four concrete values:

| Resolution | Meaning |
|---|---|
| `ResolvedColumn` | One matching declared column and its relation occurrence |
| `CandidateColumn` | Possible owners whose declarations were not supplied |
| `MissingColumn` | No matching visible column in the supplied information |
| `AmbiguousColumn` | Multiple declared columns match |

A missing or ambiguous reference has `Type\InvalidReference`, carrying a diagnosis.
It does not become an unknown type. `Type\Undetermined` names missing information:
absent declarations, an unsupplied parameter, or a NULL literal without an imposed
type. Unimplemented semantics raise `Core\SemanticException`; they never produce a
successful unknown statement, property, or expression.

`Name` holds a decoded semantic identifier and its quoting delimiter. When building
declarations directly, supply the resolved name and preserve quoting for names
whose case or punctuation requires it. SQL input is decoded using the dialect's
name rules.

See [statement models](statements.md) for immutable updates and the current
implementation boundary, and [semantic model](semantic-model.md) for the contract
and its verification obligations.

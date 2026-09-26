# Dependency layers

`k-kinzal/sql-semantics` owns the shared `Core` contracts, schema binding,
`Facade\Semantics`, and `Statement` values/writer. It receives a `Core\Dialect`
from a database package or an application. It does not select concrete databases.

Each database package owns `SqlSemantics\Platform\<Database>`, including its
`Dialect` enum, and `SqlSemantics\Statement\Model\<Database>`. Its platform
constructs the matching sql-parser implementation and loads a construction map
from its own `resources/mapping` directory. `ValueReader::fromFile()` consumes
that explicit path; the common runtime does not search sibling package paths.

Deptrac runs independently in all four packages. The complete `Statement`
namespace, including generated model files, can depend only on itself. The
common Core layer can depend on parser core contracts and Statement. Each
platform can depend on common Core, parser core contracts, and its own parser.
Generated values cannot retain parser objects or call the analyzer when printing.

Database tests and fuzz targets belong to their database package. Common tests
use a test-only dialect selector; it is never part of the runtime autoload map.

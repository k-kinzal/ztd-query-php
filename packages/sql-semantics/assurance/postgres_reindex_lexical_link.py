"""Link the formal rebuild spelling to the exact audited PHP rendering paths."""

import ast
import json
import re

from run import TOOL, digest, require

ROOTS = {
    "Schema/Index/ReindexIndex": ("INDEX", "target", True),
    "Schema/Index/ReindexTable": ("TABLE", "target", True),
    "Schema/Index/Partition/ReindexPartitionTable": ("TABLE", "target", True),
    "Schema/Index/Scope/ReindexSchema": ("SCHEMA", "schema", True),
    "Schema/Index/Scope/ReindexDatabase": ("DATABASE", "database", True),
    "Schema/Index/Scope/ReindexSystemCatalogs": ("SYSTEM", "database", False),
}
COORDINATES = {
    "Schema/Index/IndexReference": "index",
    "Schema/Index/ConstraintIndexReference": "index",
    "Schema/Index/ExclusionIndexReference": "index",
    "Schema/Index/Partition/PartitionIndexReference": "index",
    "Schema/Index/ReindexTableTarget": "name",
    "Schema/TableName": "table",
}
TEMPLATES = (
    "identifier-boundaries-v1.json",
    "postgres-drop-index-boundaries-v3.json",
    "postgres-reindex-index-boundaries-v2.json",
    "postgres-reindex-table-boundaries-v1.json",
    "postgres-reindex-partition-index-boundaries-v1.json",
    "postgres-reindex-exclusion-index-boundaries-v1.json",
    "postgres-reindex-verbose-boundaries-v1.json",
    "postgres-reindex-lexical-boundaries-v1.json",
)
PHP_SOURCE = "packages/sql-semantics-postgres/src/Ir"
MODE = r"\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode"


def expected():
    fixed = {"Identifier::quoted": """return '"' . str_replace('"', '""', $this->value) . '"';"""}
    for role, field in COORDINATES.items():
        fixed[role + "::quoted"] = "return $this->schema->quoted() . '.' . $this->" + field + "->quoted();"
    for role, (operator, field, has_mode) in ROOTS.items():
        locking = f"($this->mode === {MODE}::Concurrent ? 'CONCURRENTLY ' : '') . " if has_mode else ""
        fixed[role + "::toString"] = ("return 'REINDEX ' . ($this->verbose ? '(VERBOSE) ' : '') . "
                                     + f"'{operator} ' . " + locking + f"$this->{field}->quoted();")
    return fixed


def verify_spelling(manifest, shape_result, specification):
    lexemes = {
        "".join(ast.literal_eval(array)) for array in re.findall(
            r"\[(?:'[A-Z ()]')(?:, '[A-Z ()]')*\]", specification.read_text()
        )
    }
    wanted = {"REINDEX ", "(VERBOSE) ", "CONCURRENTLY ", *(item[0] + " " for item in ROOTS.values())}
    require(lexemes == wanted, "postgres-reindex-lexical-token-correspondence", sorted(lexemes))
    require('def quote : Char := \'"\'\n' in specification.read_text(), "postgres-reindex-lexical-quote-correspondence")

    checked = set(shape_result["checkedBoundaries"])
    templates, receipts = {}, {}
    for name in TEMPLATES:
        path = TOOL / name
        receipts[name] = digest(path)
        for role, rule in json.loads(path.read_text())["rules"].items():
            templates[role] = rule.get("common", []) + rule.get("postgres", [])
    required = expected()
    require(set(required) <= checked and "Identifier::__construct" in checked, "postgres-reindex-lexical-php-coverage",
            sorted(set(required) - checked))
    for role, body in required.items():
        require(templates.get(role) == [body], "postgres-reindex-lexical-php-template", role)
    files = {f"{PHP_SOURCE}/{method.partition('::')[0]}.php" for method in required}
    files.add(f"{PHP_SOURCE}/Schema/Index/IndexRebuildMode.php")
    return {"methods": required, "files": {name: manifest[name] for name in sorted(files)}, "templates": receipts,
            "lexemes": sorted(lexemes), "constructorPrecondition": "Identifier::__construct"}

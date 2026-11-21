"""Compare the fixed grammar scope with an independently extracted PHP inventory."""

import json

from pathlib import Path

TOOL = Path(__file__).resolve().parent

ROOTS = {
    "SqlSemantics\\Platform\\MySql\\Ir\\Select",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Select",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Select",
}


def public_scope(packages, distribution):
    required = json.loads((TOOL / "required-grammars.json").read_text())["artifacts"]
    actual = {name: sha256 for name, sha256 in distribution.items() if "/resources/productions/" in name and name.endswith(".php")}
    changed = sorted(name for name in set(required) | set(actual) if required.get(name, {}).get("sha256") != actual.get(name))
    by_class = {name: record for package in packages.values() for name, record in package["classes"].items()}

    def implements_ir(name, seen):
        if name == "SqlSemantics\\SemanticsIR":
            return True
        if name in seen or name not in by_class:
            return False
        record = by_class[name]
        return any(implements_ir(target, seen | {name}) for target in record["roles"] + record["parent_types"])

    concrete = {name: record for name, record in by_class.items() if not record["abstract"] and record["kind"] == "Stmt_Class"}
    all_roots = {name for name in concrete if implements_ir(name, set())}
    graph_candidates = sorted(name for name, record in by_class.items()
                              if not record["abstract"] and record["kind"] in ("Stmt_Class", "Stmt_Enum")
                              and record["visibility"] == "public" and "\\Ir\\" not in name)
    return {
        "requiredGrammarArtifacts": required,
        "changedGrammarArtifacts": changed,
        "completeRootTypes": sorted(all_roots),
        "unregisteredRootTypes": sorted(all_roots - ROOTS),
        "missingRootTypes": sorted(ROOTS - all_roots),
        "publicTypesOutsideCompletedIr": graph_candidates,
        "evidenceDomain": "dialect-owned signed-integer projections (MySQL, PostgreSQL), SQLite projection domain",
        "openRules": "All grammar alternatives and class roles without constructor, observation and evidence records remain open.",
        "coverageClassification": "partial",
    }

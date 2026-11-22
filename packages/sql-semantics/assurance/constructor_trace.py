"""Connect each audited public value role to its contracts and observed evidence."""

import json

from run import digest, require
from scope import CONTRACTS, public_scope


DIALECTS = {"MySql": "mysql", "PostgreSql": "postgres", "Sqlite": "sqlite"}
FIELDS = {
    "role": ("role", "roles"),
    "structure": ("structure", "fields", "values"),
    "conditions": ("conditions", "invariants", "boundary", "local", "composition"),
    "meaning": ("meaning",),
    "sql": ("sql",),
}
OBLIGATIONS = {
    "P01": "resolved-identity",
    "P02": "checked-construction",
    "P03": "closed-value-dependencies",
    "P04": "semantic-composition",
    "P05": "deep-immutability",
    "P06": "faithful-sql-representation",
    "Q01": "constructor-soundness",
    "Q02": "required-domain-admission",
    "Q03": "composition-soundness",
    "Q04": "total-and-faithful-emission",
    "Q05": "installed-implementation-identity",
}
SCOPE_CHECKS = (
    "changedGrammarArtifacts", "unregisteredRootTypes", "missingRootTypes",
    "unregisteredConstructors", "missingConstructors", "unexpectedPublicationRoutes",
)


def check_scope(inventory, scope, distribution):
    for package in inventory.values():
        for name, value in package["classes"].items():
            require(distribution.get(value["file"]) == value["sha256"], "trace-source-mismatch", name)
    measured = public_scope(inventory, distribution)
    require(measured == scope, "trace-scope-mismatch")
    require(all(not scope[field] for field in SCOPE_CHECKS), "trace-unregistered-role-or-path",
            {field: scope[field] for field in SCOPE_CHECKS if scope[field]})


def role_declaration(record, name, role):
    declarations = record["constructors"][role]
    pointer = "/constructors/" + role.replace("~", "~0").replace("/", "~1")
    indexed = {field: [f"{pointer}/{key}" for key in options if declarations.get(key)]
               for field, options in FIELDS.items()}
    if declarations.get("values"):
        indexed["conditions"].append(f"{pointer}/values")
    planned = {key: [f"/evidence/{key}"] for key in OBLIGATIONS if isinstance(record.get("evidence"), dict)
               and record["evidence"].get(key)}
    for key in declarations.get("obligations", ()):
        require(key in OBLIGATIONS, "trace-unknown-obligation", [name, role, key])
        planned.setdefault(key, []).append(f"{pointer}/obligations")
    return {
        "contract": name, "role": role, "declaredFields": indexed,
        "undeclaredRoleFields": [field for field, paths in indexed.items() if not paths],
        "declaredObligations": planned,
        "undeclaredObligations": sorted(OBLIGATIONS.keys() - planned.keys()),
    }


def checked_methods(guard, dialect, role):
    if guard is None:
        return None
    _, origin, report = guard
    audited = report["results"][dialect]
    healthy = audited["healthy"]
    require(healthy["status"] == "verified", "trace-invalid-shape-audit", dialect)
    if role not in healthy["types"]:
        return None
    role = role.replace("\\", "/")
    prefix = role + "::"
    return {
        "audit": origin,
        "boundaryTemplates": [method for method in healthy["checkedBoundaries"] if method.startswith(prefix)],
        "checkedLists": healthy["checkedLists"].get(role, []),
        "faults": [fault["name"] for fault in audited["faults"] if fault["role"].startswith(prefix)],
    }


def installed_roots(staged, roots, manifest):
    if staged is None:
        return {}
    _, origin, report = staged
    installed = {}
    for profile, evidence in report["profiles"].items():
        measured = set()
        for case in evidence["runtime"]:
            name = f"SqlSemantics\\Platform\\{case['platform']}\\Ir\\{case['role']}"
            source = case["path"].removeprefix("/stage/vendor/k-kinzal/")
            require(name in roots and name not in measured and manifest.get("packages/" + source) == roots[name]["sha256"],
                    "trace-installed-root-mismatch", {"profile": profile, "role": name, "path": source})
            measured.add(name)
            installed.setdefault(name, []).append({"stagedInstallation": origin, "profile": profile, "runtimeProfile": case["profile"]})
        require(measured == roots.keys(), "trace-missing-installed-root", sorted(roots.keys() - measured))
    require(set(report["profiles"]) == {"main", "tag-example"}, "trace-missing-installation-profile")
    return installed


def trace(scope, manifest, tools, sqlite, native, guards, staged):
    native_links = {item["contract"]: item for item in native.values()}
    sqlite_links = {} if sqlite is None else sqlite["domains"]
    records = {}
    contracts = {}
    for name, sha256 in CONTRACTS.items():
        path = tools / name
        require(digest(path) == sha256, "trace-contract-digest-mismatch", name)
        record = json.loads(path.read_text())
        records[name] = record
        receipts = {}
        if name in native_links:
            receipts["native"] = native_links[name]
        if name in sqlite_links:
            receipts["sqlite"] = sqlite_links[name]
        if name == "server-projection-admission-v1.json":
            receipts.update({role: native[role] for role in ("projection", "grouped") if role in native})
        contracts[name] = {
            "sha256": sha256, "revision": record["revision"], "approval": record["approval"],
            "domainEvidence": receipts,
            "evidenceKind": "related-domain" if receipts else "unconfirmed",
            "contractFields": sorted(record.keys() - {"constructors"}),
        }

    values = scope["registeredConstructors"]
    installed = installed_roots(staged, {name: values[name] for name in scope["completeRootTypes"]}, manifest)
    constructors = {}
    for name, item in values.items():
        _, _, dialect, _, role = name.split("\\", 4)
        constructors[name] = {
            "dialect": DIALECTS[dialect], "publicAndMagicSignatures": item["paths"],
            "source": item["source"], "sourceDigest": item["sha256"],
            "publishers": [method for method, results in scope["publicationRoutes"].items() if name in results],
            "declarations": [role_declaration(records[row["record"]], row["record"], row["role"])
                             for row in item["contracts"]],
            "supplementalContracts": item["supplementalEvidence"],
            "auditedShape": checked_methods(guards, DIALECTS[dialect], role),
            "installedRootExamples": installed.get(name, []),
        }
    gaps = {field: sum(any(declaration[field] for declaration in item["declarations"]) for item in constructors.values())
            for field in ("undeclaredRoleFields", "undeclaredObligations")}
    return {"obligations": OBLIGATIONS, "contracts": contracts, "constructors": constructors,
            "rolesWithDeclarationGaps": gaps, "status": "provisional-constructor-trace"}

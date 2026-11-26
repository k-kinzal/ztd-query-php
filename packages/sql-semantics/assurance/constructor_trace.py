"""Connect each audited public value role to its contracts and observed evidence."""

import json

from compare_assurance_revisions import EVIDENCE_CONTRACTS, evidence_reference
from run import digest, require
from scope import CONTRACTS, public_scope


DECLARATIONS = "constructor-declarations-v45.json"
DECLARATION_DIGEST = "2096d1579d58fb2a96a7248c82331ffe82a3eb024f695fcda449b09faa17e6d1"
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
    "unregisteredConstructors", "missingConstructors", "unexpectedPublicationRoutes", "unexpectedUnconstrainedRoutes",
)


def check_scope(inventory, scope, distribution):
    for package in inventory.values():
        for name, value in package["classes"].items():
            require(distribution.get(value["file"]) == value["sha256"], "trace-source-mismatch", name)
    measured = public_scope(inventory, distribution)
    require(measured == scope, "trace-scope-mismatch")
    require(all(not scope[field] for field in SCOPE_CHECKS), "trace-unregistered-role-or-path",
            {field: scope[field] for field in SCOPE_CHECKS if scope[field]})


def declaration_contract(path):
    require(digest(path) == DECLARATION_DIGEST, "trace-declaration-contract-mismatch", str(path))
    result = json.loads(path.read_text())
    require(set(result["obligations"]) == OBLIGATIONS.keys(), "trace-declaration-obligations-incomplete")
    for rule in result["obligations"].values():
        require(bool(rule.get("criterion")) and bool(rule.get("checks")), "trace-missing-criterion", rule)
    return result


def pointer(value):
    return value.replace("~", "~0").replace("/", "~1")


def role_declaration(record, name, role, supplement):
    declarations = record["constructors"][role]
    prefix = "/constructors/" + pointer(role)
    found = {field: [{"contract": name, "pointer": f"{prefix}/{key}"} for key in options if declarations.get(key)]
             for field, options in FIELDS.items()}
    if declarations.get("values"):
        found["conditions"].append({"contract": name, "pointer": f"{prefix}/values"})
    for field, description in supplement.get(name, {}).get(role, {}).items():
        require(field in found and not found[field] and bool(description), "trace-invalid-supplement", [name, role, field])
        found[field].append({"contract": DECLARATIONS, "pointer": f"/additions/{pointer(name)}/{pointer(role)}/{field}"})
    require(all(found.values()), "trace-role-field-missing", {"contract": name, "role": role,
                                                            "fields": [field for field, refs in found.items() if not refs]})
    planned = {key: [{"contract": DECLARATIONS, "pointer": f"/obligations/{key}"}] for key in OBLIGATIONS}
    for key in declarations.get("obligations", ()):
        require(key in OBLIGATIONS, "trace-unknown-obligation", [name, role, key])
        planned[key].append({"contract": name, "pointer": f"{prefix}/obligations"})
    for section in ("evidence", "obligationEvidence"):
        if isinstance(record.get(section), dict):
            for key in OBLIGATIONS.keys() & record[section].keys():
                planned[key].append({"contract": name, "pointer": f"/{section}/{key}"})
    return {"contract": name, "role": role, "declaredFields": found, "declaredObligations": planned}


def active_declarations(tools):
    catalog = declaration_contract(tools / DECLARATIONS)
    supplements = catalog["additions"]
    unknown = sorted(supplements.keys() - CONTRACTS.keys())
    require(not unknown, "trace-unregistered-supplement", unknown)
    records, roles = {}, {}
    for name, sha256 in CONTRACTS.items():
        path = tools / name
        require(digest(path) == sha256, "trace-contract-digest-mismatch", name)
        record = json.loads(path.read_text())
        require(supplements.get(name, {}).keys() <= record["constructors"].keys(),
                "trace-unregistered-supplement-role", name)
        records[name] = record
        roles[name] = {role: role_declaration(record, name, role, supplements) for role in record["constructors"]}
    receipt = {
        "record": DECLARATIONS, "sha256": DECLARATION_DIGEST, "revision": catalog["revision"],
        "approval": catalog["approval"], "contracts": len(records),
        "declarations": sum(map(len, roles.values())),
    }
    return receipt, records, roles


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
        **({"checkedBindingSources": healthy["checkedBindingSources"]} if (dialect, role) in {
            ("mysql", "Schema/Alter/RenameTable"), ("mysql", "From/Varchar"), ("mysql", "From/TextColumnReference"), ("mysql", "TableSource"),
            ("sqlite", "Predicate/FoldedMembership"), ("sqlite", "Schema/Generation/ColumnGeneration"), ("sqlite", "Schema/View/CreateView"), ("postgres", "Expression/WideIntegerLiteral"),
            ("postgres", "Expression/BuiltinCast"),
            ("sqlite", "Schema/Index/CreateIndex"), ("sqlite", "Schema/Index/DropIndex"),
            ("sqlite", "Schema/Index/IndexReference"), ("sqlite", "Schema/Index/MissingIndex"),
            ("sqlite", "Maintenance/Analyze"), ("sqlite", "Maintenance/Reindex"), ("sqlite", "Maintenance/FlagPragma"), ("sqlite", "Maintenance/Inventory/InventoryPragma"), ("sqlite", "Maintenance/Metadata/TableInspection"), ("sqlite", "Maintenance/Metadata/IndexInspection"), ("sqlite", "Maintenance/Metadata/ForeignKeyAudit"), ("sqlite", "Maintenance/Integrity/IntegrityAudit"), ("sqlite", "Maintenance/Header/HeaderPragma"), ("sqlite", "Maintenance/Vacuum/Vacuum"), ("sqlite", "Connection/Attach"), ("sqlite", "Connection/Detach"), ("sqlite", "Maintenance/Page/PagePragma"), ("sqlite", "Maintenance/Reclaim/AutoVacuum"), ("sqlite", "Maintenance/Reclaim/IncrementalVacuum"),
        ("sqlite", "Maintenance/Cache/CacheSize"), ("sqlite", "Maintenance/Cache/CacheSpill"), ("sqlite", "Maintenance/Cache/DefaultCacheSize"), ("sqlite", "Schema/Trigger/CreateTrigger"), ("sqlite", "Schema/Trigger/DropTrigger"), ("sqlite", "Maintenance/Metadata/TableListing"), ("sqlite", "Maintenance/RelationTarget"), ("sqlite", "Maintenance/IndexTarget"),
        } else {}),
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


def installed_roles(staged, constructors):
    if staged is None:
        return {}
    _, origin, report = staged
    installed = {name: [] for name in constructors}
    for profile, evidence in report["profiles"].items():
        values = {name: record for name, record in evidence["roles"].items() if record["kind"] != "interface"}
        require(values.keys() == constructors.keys(), "trace-installed-role-scope", profile)
        for name, actual in values.items():
            declared = constructors[name]
            expected = "/stage/vendor/k-kinzal/" + declared["source"].removeprefix("packages/")
            require(actual["path"] == expected and actual["sha256"] == declared["sha256"],
                    "trace-installed-role-mismatch", {"role": name, "profile": profile})
            installed[name].append({"stagedInstallation": origin, "profile": profile, **actual})
    return installed


def trace(scope, manifest, tools, sqlite, native, guards, staged):
    declaration_index, records, declarations = active_declarations(tools)
    native_links = {item["contract"]: item for item in native.values()}
    schedules = {}
    for name in sorted(native_links.keys() & EVIDENCE_CONTRACTS.keys()):
        path = tools / name
        require(digest(path) == EVIDENCE_CONTRACTS[name], "trace-evidence-contract-digest-mismatch", name)
        owner = evidence_reference(json.loads(path.read_text()), CONTRACTS, name)
        schedules.setdefault(owner, {})[name] = native_links[name]
    sqlite_links = {} if sqlite is None else sqlite["domains"]
    contracts = {}
    for name, record in records.items():
        receipts = {}
        if name in native_links:
            receipts["native"] = native_links[name]
        if name in schedules:
            receipts["nativeSchedules"] = schedules[name]
        if name in sqlite_links:
            receipts["sqlite"] = sqlite_links[name]
        if name == "server-projection-admission-v1.json":
            receipts.update({role: native[role] for role in ("projection", "grouped") if role in native})
        contracts[name] = {
            "sha256": CONTRACTS[name], "revision": record["revision"], "approval": record["approval"],
            "domainEvidence": receipts,
            "evidenceKind": "related-domain" if receipts else "unconfirmed",
            "contractFields": sorted(record.keys() - {"constructors"}),
            "profileFields": [field for field in ("profile", "profiles", "initialExecutableDomain") if record.get(field)],
        }

    contracts[DECLARATIONS] = {"sha256": DECLARATION_DIGEST, "revision": declaration_index["revision"], "approval": declaration_index["approval"],
                               "evidenceKind": "required-criteria", "domainEvidence": {}}
    values = scope["registeredConstructors"]
    installed = installed_roots(staged, {name: values[name] for name in scope["completeRootTypes"]}, manifest)
    measured = installed_roles(staged, values)
    constructors = {}
    for name, item in values.items():
        _, _, dialect, _, role = name.split("\\", 4)
        constructors[name] = {
            "dialect": DIALECTS[dialect], "publicAndMagicSignatures": item["paths"],
            "enum": item["enum"],
            "source": item["source"], "sourceDigest": item["sha256"],
            "publishers": [method for method, results in scope["publicationRoutes"].items() if name in results],
            "declarations": [declarations[row["record"]][row["role"]] for row in item["contracts"]],
            "supplementalContracts": item["supplementalEvidence"],
            "auditedShape": checked_methods(guards, DIALECTS[dialect], role),
            "installedRootExamples": installed.get(name, []),
            "installedRoleSources": measured.get(name, []),
        }
    return {"obligations": OBLIGATIONS, "contracts": contracts, "constructors": constructors, "activeDeclarations": declaration_index,
            "status": "provisional-constructor-trace"}

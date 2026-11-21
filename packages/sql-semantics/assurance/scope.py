"""Compare the fixed grammar scope with an independently extracted PHP inventory."""

import hashlib
import json

from pathlib import Path

TOOL = Path(__file__).resolve().parent

ROOTS = {
    "SqlSemantics\\Platform\\MySql\\Ir\\Select",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Select",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Select",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Mutation\\Delete",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Rows\\ValuesQuery",
}


CONTRACTS = {
    "contract-v1.json": "7f607339a5a5424cfc1dba25823cab5cc469c20e1e01d3b314755801a6f9b849",
    "server-projection-v1.json": "4f46d75542c1cc47518a2a91c8faaf318800891281e3b06ef409d9f9236f3e97",
    "sqlite-relational-v1.json": "b4a4f07874e99eea615fd3e981b54d2de0c9c1ca27d9d5ff3e822b92d4a1596d",
    "sqlite-result-v1.json": "8cd76f55067fe32c08d14b648be3a0b886b6e34785db7bb8707f20edc2c37237",
    "sqlite-delete-v1.json": "60274d9a4bf82c40f5363ead740a7465bba8bca359dfe9ead7e35f8de185cb7b",
    "sqlite-values-v1.json": "2c1f212e5fae261258dc8c0fb450fd663a41fffc965c7988346b6dcfa39c6ad3",
}
PUBLICATION = "dbecb7ed3e76fd6ed1753ec1f914e4f5f9c4e164661106b8bb2939da2d7529c4"


def constructor_scope(by_class):
    definitions = {}
    publication = (TOOL / "publication-v2.json").read_bytes()
    if hashlib.sha256(publication).hexdigest() != PUBLICATION:
        raise ValueError("Publication contract differs from its recorded revision")
    publication = json.loads(publication)
    prefixes = tuple("SqlSemantics\\Platform\\" + dialect for dialect in publication["dialects"])
    routes = {publication["analyzerInterface"]: [publication["interfaceResult"]]}
    sqlite_roots = []
    for name, sha256 in CONTRACTS.items():
        raw = (TOOL / name).read_bytes()
        if hashlib.sha256(raw).hexdigest() != sha256:
            raise ValueError(f"Constructor contract differs from its recorded revision: {name}")
        contract = json.loads(raw)
        sqlite_roots.extend("Ir\\" + root.replace("/", "\\") for root in contract.get("additionalPublicRoots", []))
        for role, record in contract["constructors"].items():
            concrete = record.get("type", role).replace("/", "\\")
            for platform in prefixes[:2] if name == "server-projection-v1.json" else prefixes[2:]:
                clas = platform + "\\Ir\\" + concrete
                definitions.setdefault(clas, []).append({"record": name, "role": role})
    for platform in prefixes:
        roles = [publication["single"]["result"]]
        if platform.endswith("\\Sqlite"):
            roles.extend(sqlite_roots)
        routes[platform + "\\" + publication["single"]["method"]] = sorted(platform + "\\" + role for role in roles)
    batch_routes = {
        platform + "\\" + publication["batch"]["method"]: ["array"] for platform in prefixes
    }
    published = {
        clas for clas, record in by_class.items()
        if record["visibility"] == "public" and record["kind"] in ("Stmt_Class", "Stmt_Enum")
        and clas.startswith(tuple(prefix + "\\Ir\\" for prefix in prefixes))
    }
    publication_types = set(definitions) | {kind for cases in routes.values() for kind in cases}
    found = {}
    for clas, record in by_class.items():
        if record["visibility"] != "public":
            continue
        for method, returns in record["public_return_roles"].items():
            matched = sorted(set(returns) & publication_types)
            if matched:
                found[f"{clas}::{method}"] = matched
        for method in record["public_array_returns"]:
            route = f"{clas}::{method}"
            if route in batch_routes:
                found[route] = ["array"]
    routes.update(batch_routes)
    return {
        "registeredConstructors": {name: {
            "contracts": definitions[name], "source": by_class[name]["file"],
            "sha256": by_class[name]["sha256"], "paths": by_class[name]["public_and_magic_signatures"],
        } for name in sorted(published & set(definitions))},
        "unregisteredConstructors": sorted(published - set(definitions)),
        "missingConstructors": sorted(set(definitions) - published),
        "publicationRoutes": found,
        "publicationContract": PUBLICATION,
        "unexpectedPublicationRoutes": {
            name: found.get(name) for name in sorted(set(routes) | set(found)) if routes.get(name) != found.get(name)
        },
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
        **constructor_scope(by_class),
        "requiredGrammarArtifacts": required,
        "changedGrammarArtifacts": changed,
        "completeRootTypes": sorted(all_roots),
        "unregisteredRootTypes": sorted(all_roots - ROOTS),
        "missingRootTypes": sorted(ROOTS - all_roots),
        "publicTypesOutsideCompletedIr": graph_candidates,
        "evidenceDomain": "dialect-owned signed-integer projections (MySQL, PostgreSQL), SQLite base-table queries, VALUES and single-target DELETE",
        "openRules": "All grammar alternatives and class roles without constructor, observation and evidence records remain open.",
        "coverageClassification": "partial",
    }

"""Compare the fixed grammar scope with an independently extracted PHP inventory."""

import hashlib
import json

from pathlib import Path

TOOL = Path(__file__).resolve().parent

ROOTS = {
    "SqlSemantics\\Platform\\MySql\\Ir\\Mutation\\Delete",
    "SqlSemantics\\Platform\\MySql\\Ir\\Rows\\Compound",
    "SqlSemantics\\Platform\\MySql\\Ir\\Schema\\CreateTable",
    "SqlSemantics\\Platform\\MySql\\Ir\\Schema\\DropTable",
    "SqlSemantics\\Platform\\MySql\\Ir\\Mutation\\Insert",
    "SqlSemantics\\Platform\\MySql\\Ir\\Mutation\\Update",
    "SqlSemantics\\Platform\\MySql\\Ir\\Transaction\\Transaction",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Mutation\\Delete",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Rows\\Compound",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Schema\\CreateTable",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Schema\\DropTable",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Mutation\\Insert",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Mutation\\Update",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Transaction\\Transaction",
    "SqlSemantics\\Platform\\MySql\\Ir\\Select",
    "SqlSemantics\\Platform\\PostgreSql\\Ir\\Select",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Select",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Mutation\\Delete",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Mutation\\Insert",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Mutation\\Update",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Rows\\ValuesQuery",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Rows\\Compound",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Schema\\DropTable",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Schema\\CreateTable",
    "SqlSemantics\\Platform\\Sqlite\\Ir\\Transaction\\Transaction",
}


CONTRACTS = {
    "server-parameter-v1.json": "a25669b690cb52522a4fc0af7f35d6fee5cb3ac597dabd72383dee3f14d77052",
    "server-create-table-v1.json": "1b9ce10f3903792d68b307e0b92d0bf3008e3ff0af150bfc4b3d8438dde23b4f",
    "server-compound-v2.json": "dbbf8f67d81e09156521992de45ac350e7dffdac774200f54fc80511c8d27bf8",
    "server-drop-table-v1.json": "6703cdd9ae829f94f64e9a4f263cb40976fb49a1c60f71f9655d6765b2f84fb7",
    "server-transaction-v1.json": "c0f7fb70d54ca3321d9104e0a239c62bdf24e48ce65febffb1906faae429dd09",
    "server-update-v1.json": "23adce1af48b017804aacbfc7de5b2df8d650a59b9645be44ca70c85319a5933",
    "server-insert-v1.json": "0456225d41ef56fa2e8271102caae8ebb225bf9c76372eaaaa18126b2d8b0f79",
    "server-result-v1.json": "e92fbdaddd2d0b7f29aff440f72bab31fa6065a93820b6ac4d57a431f313056c",
    "server-join-v1.json": "1dd8c227ba1638bb1591d4f12c3f9f199ba85e50fc6d24fb9e03b57ff8705b71",
    "server-delete-v1.json": "13b9d85f1893ef514ddedd636212b9bc8289980871139c4dad6176e4569b734a",
    "sqlite-recursive-source-v1.json": "2c5b8873c4d6b80e6f81f3db5423539057e29117e8577bd3f887b6f43f3c52d3",
    "sqlite-compound-v1.json": "c814b6f5abcd51569cd56a6ce03743c595d513964a6191fef253c488176dc24f",
    "sqlite-scalars-v1.json": "a8e832b1a01761446f8e21df232ead7e90a9c239fd09ce1f3ea477904e8cb52f",
    "contract-v1.json": "7f607339a5a5424cfc1dba25823cab5cc469c20e1e01d3b314755801a6f9b849",
    "server-projection-v1.json": "4f46d75542c1cc47518a2a91c8faaf318800891281e3b06ef409d9f9236f3e97",
    "sqlite-relational-v1.json": "b4a4f07874e99eea615fd3e981b54d2de0c9c1ca27d9d5ff3e822b92d4a1596d",
    "sqlite-result-v1.json": "8cd76f55067fe32c08d14b648be3a0b886b6e34785db7bb8707f20edc2c37237",
    "sqlite-delete-v1.json": "60274d9a4bf82c40f5363ead740a7465bba8bca359dfe9ead7e35f8de185cb7b",
    "sqlite-values-v1.json": "2c1f212e5fae261258dc8c0fb450fd663a41fffc965c7988346b6dcfa39c6ad3",
    "sqlite-local-source-v1.json": "d177e36ff5d5bf4d5de779b9f91d69f91f459c41715a73228ec7c655703829bd",
    "sqlite-insert-v1.json": "a6ce28637ff31c61eb636d0e14170aea7c6dff83a2365c98bbdad94f8ec955da",
    "sqlite-update-v1.json": "71d081d6b35c5b38855b343617660ebb07e49f5aeb5775c6feed047208478f1d",
    "sqlite-drop-table-v1.json": "622ae8a9601acc4c63f10e7151891dc6289062c9c3393ec78e6c6eb1a1fff0c2",
    "sqlite-create-table-v1.json": "8e08714c71d4a067cfeaa0ae18d60c3715ae468941461f1bd1b90908a9f09e76",
    "sqlite-transaction-v1.json": "52ba3cc00a6b7428a85d1ab7d9a55dd04cb9fcbecf0909ccc94a86e40c684afc",
}
PUBLICATION = "70420e5ebdc789b2855bf47a4c4fbf76253975e5c3d8aad710eb8af17d0d039a"


SQLITE_LITERAL_TYPES = {
    role: "Literal/" + role for role in ("BlobLiteral", "HexIntegerLiteral", "RealLiteral", "TextLiteral")
}


def constructor_scope(by_class):
    definitions = {}
    publication = (TOOL / "publication-v10.json").read_bytes()
    if hashlib.sha256(publication).hexdigest() != PUBLICATION:
        raise ValueError("Publication contract differs from its recorded revision")
    publication = json.loads(publication)
    prefixes = tuple("SqlSemantics\\Platform\\" + dialect for dialect in publication["dialects"])
    routes = {publication["analyzerInterface"]: [publication["interfaceResult"]]}
    roots_by_dialect = {dialect: [] for dialect in publication["dialects"]}
    for name, sha256 in CONTRACTS.items():
        raw = (TOOL / name).read_bytes()
        if hashlib.sha256(raw).hexdigest() != sha256:
            raise ValueError(f"Constructor contract differs from its recorded revision: {name}")
        contract = json.loads(raw)
        dialects = contract.get("dialects", ("MySql", "PostgreSql") if name == "server-projection-v1.json" else ("Sqlite",))
        for dialect in dialects:
            roots_by_dialect[dialect].extend("Ir\\" + root.replace("/", "\\") for root in contract.get("additionalPublicRoots", []))
        for role, record in contract["constructors"].items():
            concrete = record.get("type", role)
            if name == "sqlite-scalars-v1.json":
                concrete = SQLITE_LITERAL_TYPES.get(concrete, concrete)
            concrete = concrete.replace("/", "\\")
            for dialect in dialects:
                clas = "SqlSemantics\\Platform\\" + dialect + "\\Ir\\" + concrete
                definitions.setdefault(clas, []).append({"record": name, "role": role})
    for platform, dialect in zip(prefixes, publication["dialects"]):
        additions = roots_by_dialect[dialect]
        if dialect != "Sqlite" and sorted(additions) != sorted(publication["single"]["additionalDialectResults"].get(dialect, [])):
            raise ValueError(f"Publication contract does not match the recorded roots for {dialect}")
        roles = [publication["single"]["result"], *additions]
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
        "evidenceDomain": "dialect-owned signed-integer projections with typed parameters, binary joins, filters, row selection, typed compounds, single-target DELETE, literal INSERT, ordered UPDATE, persistent table creation and removal and basic transactions (MySQL, PostgreSQL), SQLite base-table queries, VALUES, single-target DELETE, INSERT, UPDATE, CREATE TABLE and DROP TABLE, ordinary local VALUES, one recursive self source, and transaction control",
        "openRules": "All grammar alternatives and class roles without constructor, observation and evidence records remain open.",
        "coverageClassification": "partial",
    }

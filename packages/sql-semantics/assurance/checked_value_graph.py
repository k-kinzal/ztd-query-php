"""Check transitive readonly storage against the independent public value inventory."""

import copy
import json

from run import Failure, TOOL, digest, require

CONTRACT = "closed-value-graph-v8.json"
CONTRACT_DIGEST = "de8de69b4b831ec616be6895a0da91d836f7e94edb1e6cf25f4734a6fa32d2b6"
DIALECTS = {"mysql": "MySql", "postgres": "PostgreSql", "sqlite": "Sqlite"}


def contract():
    path = TOOL / CONTRACT
    require(digest(path) == CONTRACT_DIGEST, "value-closure-contract-mismatch")
    return json.loads(path.read_text())


def field_types(owner, field, declared, rules, verified_methods, specification):
    if declared != ["array"]:
        require("array" not in declared and field not in rules, "value-closure-unspecified-array", [owner, field])
        members = [value.removeprefix("?") for value in declared]
        types = [member if member.startswith("SqlSemantics\\") else member.replace("\\", "/") for member in members]
        return types, "property"
    require(field in rules, "value-closure-unspecified-array", [owner, field])
    rule = rules[field]
    if isinstance(rule, list):
        element, minimum = rule
        require(isinstance(element, str) and type(minimum) is int and minimum >= 0, "value-closure-invalid-list", [owner, field])
        return [element], "checked-list-copy"
    special = specification["specialLists"].get(rule)
    require(special is not None and (owner, field) == (special["owner"], special["field"])
            and special["template"] in verified_methods, "value-closure-unverified-list", [owner, field])
    return special["elements"], rule


def verify(manifest, constructors, outcomes, specification):
    require(set(outcomes) == set(DIALECTS), "value-closure-dialect-inventory")
    primitives = set(specification["primitiveTypes"])
    result = {}
    for dialect, platform in DIALECTS.items():
        registration = specification["externalValues"][dialect]
        external = registration["name"]
        require(manifest.get(registration["source"]) == registration["sha256"], "value-closure-external-definition", registration)
        namespace = f"SqlSemantics\\Platform\\{platform}\\Ir\\"
        public_roles = {name.removeprefix(namespace).replace("\\", "/") for name in constructors if name.startswith(namespace)}
        internal_roles = set(specification["internalValues"][dialect])
        source = outcomes[dialect]
        require(source["status"] == "verified", "value-closure-source-audit", dialect)
        values = {key.replace("\\", "/"): value for key, value in source["checkedValues"].items()}
        expected = public_roles | internal_roles
        require(not public_roles & internal_roles and set(values) == expected, "value-closure-typed-inventory",
                {"dialect": dialect, "missing": sorted(expected - values.keys()), "extra": sorted(values.keys() - expected)})
        checked = {}
        for role, item in values.items():
            fields, rules = item["fields"], item["lists"]
            require(item["kind"] in ("enum", "final-readonly-fields") and (item["kind"] != "enum" or not fields),
                    "value-closure-kind", role)
            require(set(rules) == {field for field, declaration in fields.items() if declaration == ["array"]},
                    "value-closure-unspecified-array", role)
            edges = {}
            for field, declared in fields.items():
                types, storage = field_types(role, field, declared, rules, source["checkedBoundaries"], specification)
                unknown = set(types) - expected - primitives - {external}
                require(types and not unknown, "value-closure-open-edge", [role, field, sorted(unknown)])
                if any(value.startswith("?") for value in declared):
                    types = types + ["null"]
                edges[field] = {"domain": types, "storage": storage}
            checked[role] = {"kind": item["kind"], "fields": edges, "public": role in public_roles}
        result[dialect] = {"values": checked, "external": registration}
    return result


def controls(manifest, constructors, outcomes, specification):
    changes = (
        ("omit-public-value", "value-closure-typed-inventory"),
        ("inject-open-child", "value-closure-open-edge"),
        ("erase-list-copy", "value-closure-unspecified-array"),
        ("erase-native-assignment-boundary", "value-closure-unverified-list"),
        ("erase-mysql-drop-target-boundary", "value-closure-unverified-list"),
        ("erase-writable-trigger-boundary", "value-closure-unverified-list"),
        ("erase-ordered-trigger-action-boundary", "value-closure-unverified-list"),
        ("change-external-definition", "value-closure-external-definition"),
    )
    evidence = []
    for name, expected in changes:
        mutated = copy.deepcopy(outcomes)
        distribution = manifest.copy()
        sqlite = mutated["sqlite"]["checkedValues"]
        if name == "omit-public-value":
            before = sqlite.pop("Parameter")
            after = None
        elif name == "inject-open-child":
            before = sqlite["Select"]["fields"]["source"]
            after = sqlite["Select"]["fields"]["source"] = ["?UnregisteredValue"]
        elif name == "erase-list-copy":
            before = sqlite["Select"]["lists"].pop("columns")
            after = None
        elif name == "erase-native-assignment-boundary":
            before = "Mutation/Update::__construct"
            mutated["mysql"]["checkedBoundaries"].remove(before)
            after = None
        elif name == "erase-mysql-drop-target-boundary":
            before = "Schema/DropTable::__construct"
            mutated["mysql"]["checkedBoundaries"].remove(before)
            after = None
        elif name == "erase-writable-trigger-boundary":
            before = "Schema/Trigger/TriggerDestination::__construct"
            mutated["sqlite"]["checkedBoundaries"].remove(before)
            after = None
        elif name == "erase-ordered-trigger-action-boundary":
            before = "Schema/Trigger/Mutation/TriggerActions::copy"
            mutated["sqlite"]["checkedBoundaries"].remove(before)
            after = None
        else:
            path = specification["externalValues"]["sqlite"]["source"]
            before = distribution[path]
            after = distribution[path] = "0" * 64
        try:
            verify(distribution, constructors, mutated, specification)
        except Failure as failure:
            require(failure.signal == expected, "value-closure-wrong-control", [name, failure.signal])
            evidence.append({"case": name, "before": before, "after": after, "expected": expected, "observed": failure.signal,
                             "detail": failure.detail})
        else:
            raise Failure("value-closure-undetected-control", name)
    return evidence


def certificate(manifest, constructors, outcomes):
    specification = contract()
    proved = verify(manifest, constructors, outcomes, specification)
    return {"status": "statically-checked-under-listed-premises", "contract": CONTRACT, "sha256": CONTRACT_DIGEST,
            "claim": specification["claim"], "trust": specification["trust"], "dialects": proved,
            "controls": controls(manifest, constructors, outcomes, specification)}

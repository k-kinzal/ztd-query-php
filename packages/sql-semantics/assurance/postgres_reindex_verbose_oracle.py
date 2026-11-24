"""Predict reporting and storage transitions from immutable inputs and native identities."""

from collections import defaultdict, deque
import re

from postgres_reindex_cases import qualified
from postgres_reindex_database_cases import FIRST_APPLICATION, FIRST_UNPINNED
from postgres_reindex_verbose_cases import coordinates, outcome, table_data
from postgres_stock_catalog import check as check_stock
from run import Failure, require
from run_server import same

RUSAGE = re.compile(r"CPU: user: [0-9]+\.[0-9]+ s, system: [0-9]+\.[0-9]+ s, elapsed: [0-9]+\.[0-9]+ s\.?")
SCOPED = {"ordinary-schema", "vacant-schema", "database", "system"}
MULTIPLE = {*SCOPED, "partition-branch"}


def identity(record):
    schema = record["schema"]
    return ("pg_temp" if schema.startswith("pg_temp_") else schema, record["name"])


def leaf_lineage(observed, root, kind):
    edges = defaultdict(list)
    for child_kind, parent_schema, parent, child_schema, child in observed["links"]:
        edges[(parent_schema, parent)].append((child_kind, (child_schema, child)))
    output = []
    nodes = deque([(None, root)])
    visited = set()
    while nodes:
        role, node = nodes.popleft()
        require(node not in visited, "postgres-verbose-lineage-repeated", node)
        visited.add(node)
        if role == kind:
            output.append(node)
        else:
            nodes.extend(edges[node])
    return output


def choose(item, before):
    selector, schema, *names = coordinates(item)
    label = item["role"]
    if selector == "INDEX":
        indexes = {identity(index): index for index in before["indexes"]}
        names = leaf_lineage(before, (schema, names[0]), "i") if label == "branch-index" else [(schema, names[0])]
        return [indexes[name] for name in names], []
    if label in SCOPED:
        owners = [
            owner for owner in before["owners"]
            if (selector == "SCHEMA" and identity(owner)[0] == schema)
            or (selector == "DATABASE" and owner["id"] >= FIRST_UNPINNED)
            or (selector == "SYSTEM" and owner["id"] < FIRST_UNPINNED)
        ]
    else:
        owners = {identity(owner): owner for owner in before["owners"]}
        names = leaf_lineage(before, (schema, names[0]), "r") if label == "partition-branch" else [(schema, names[0])]
        owners = [owners[name] for name in names]
    return [], owners


def info(kind, name, resources):
    return ["INFO", "00000", f'{kind} "{name}" was reindexed', "resource-usage" if resources else None, None]


def trace(item, before):
    """Return each selected index and whether native concurrent rebuilding replaces its identity."""
    requested = item["concurrent"]
    verbose = item["verbose"]
    named, owners = choose(item, before)
    storage = {owner["id"]: owner["mode"] for owner in before["owners"]}
    planned = {}
    notices = []
    for index in named:
        concurrent = requested and storage[index["owner"]] != "t"
        planned[index["id"]] = concurrent
        if verbose:
            name = index["schema"] + "." + index["name"] if concurrent else index["name"]
            notices.append(info("index", name, True))

    by_owner = defaultdict(list)
    for entry in before["indexes"]:
        by_owner[entry["owner"]].append(entry)
    for owner in owners:
        concurrent = requested and owner["mode"] != "t"
        main = by_owner[owner["id"]]
        auxiliary = by_owner[owner["toast"]]
        if concurrent:
            require(all(index["valid"] and not index["exclusion"] for index in (*main, *auxiliary)),
                    "postgres-verbose-fixture-strict-indexes", identity(owner))
        indexes = [*auxiliary, *main] if item["release"] == "pg-17.2" and not concurrent else [*main, *auxiliary]
        for index in indexes:
            planned[index["id"]] = concurrent
            if verbose:
                name = index["schema"] + "." + index["name"] if concurrent else index["name"]
                notices.append(info("index", name, not concurrent))
        if verbose and indexes and (concurrent or item["role"] in MULTIPLE):
            notices.append(info("table", owner["schema"] + "." + owner["name"], concurrent))
    if item["role"] != "vacant-schema":
        require(bool(planned), "postgres-verbose-missing-target", coordinates(item))
    return planned, notices


def observed_notices(frames):
    converted = []
    for severity, state, message, detail, hint in frames:
        normalized = "resource-usage" if type(detail) is str and RUSAGE.fullmatch(detail) else detail
        converted.append([severity, state, message, normalized, hint])
    return converted


def check_catalogs(item, first, *others):
    for phase in (first, *others):
        if phase is None:
            continue
        same(first["stockCatalog"]["build"], phase["stockCatalog"]["build"])
        identities = {identity(index): [str(index["owner"]), str(index["id"])] for index in phase["indexes"]}
        catalog = check_stock(item["release"], phase["stockCatalog"], identities)
        expected = [list(entry[:2]) + [int(entry[3]), entry[6], entry[7]] for entry in catalog]
        obtained = [[index["schema"], index["name"], index["owner"], index["valid"], index["exclusion"]]
                    for index in phase["indexes"] if index["owner"] < FIRST_APPLICATION]
        same(sorted(expected), sorted(obtained))


def check_fixture(item, first):
    owners = {identity(owner): owner for owner in first["owners"]}
    indexes = {identity(index): index for index in first["indexes"]}
    data = table_data(item)
    same({qualified(*name): [list(row) for row in values] for name, values in data.items()}, first["rows"])
    for namespace, roles in (("fixture", (item["tables"]["source"], item["tables"]["leaves"][0])),
                             ("remote", (item["tables"]["leaves"][1],)),
                             ("pg_temp", (item["tables"]["temp"],))):
        for role in roles:
            owner = owners[namespace, role["name"]]
            require(owner["mode"] == ("t" if namespace == "pg_temp" else "p")
                    and bool(owner["toast"]), "postgres-verbose-wrong-owner-fixture", owner)
            same(sorted(role["indexes"]), sorted(i["name"] for i in indexes.values() if i["owner"] == owner["id"]))


def check_storage(before, after, plan):
    same(sorted(before["owners"], key=lambda owner: owner["id"]), sorted(after["owners"], key=lambda owner: owner["id"]))
    same(sorted(before["links"]), sorted(after["links"]))
    same(before["rows"], after["rows"])
    previous = {identity(index): index for index in before["indexes"]}
    latest = {identity(index): index for index in after["indexes"]}
    same(len(previous), len(before["indexes"]))
    same(len(latest), len(after["indexes"]))
    same(sorted(previous), sorted(latest))
    for key in sorted(previous):
        first, last = previous[key], latest[key]
        same({field: value for field, value in first.items() if field not in ("id", "file")},
             {field: value for field, value in last.items() if field not in ("id", "file")})
        wanted = [first["id"] in plan, plan.get(first["id"], False)]
        measured = [first["file"] != last["file"], first["id"] != last["id"]]
        require(wanted == measured, "postgres-verbose-storage", {"index": key, "expected": wanted, "observed": measured})


def check_observation(item, actual, sql):
    target = outcome(item)
    require(actual.get("status") == target["status"], "postgres-verbose-observer-scope", {"sql": sql, "observed": actual})
    try:
        same(target, {key: actual[key] for key in target})
        initial = actual["before"]
        check_catalogs(item, initial, actual["during"], actual["settled"])
        check_fixture(item, initial)
        if target["sqlState"] != "00000":
            same([], actual["notices"])
            same(None, actual["during"])
            check_storage(initial, actual["settled"], {})
            return
        plan, reports = trace(item, initial)
        same(reports, observed_notices(actual["notices"]))
        check_storage(initial, actual["during"], plan)
        check_storage(initial, actual["settled"], {} if item["active"] else plan)
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": sql, "expected": target, "observed": actual,
                                          "difference": error.detail}) from error

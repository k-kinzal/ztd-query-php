"""Check the exact PostgreSQL stock catalog data for each observed native build."""

from functools import cache
import json

from run import Failure, TOOL, digest, require
from run_server import same

CATALOG_REVISION = "postgres-stock-reindex-catalog-v2.json"
CATALOG_DIGEST = "de2800e7bd42fecbbedf5320065fa533fce0b32b40a4dc89e00b16ab0abd52c5"
TOAST_EDGES = """SELECT s.nspname,h.relname,h.oid,t.relname,t.oid,c.relname,c.oid,
  t.relpersistence,t.relisshared,i.indisvalid
FROM pg_catalog.pg_class h JOIN pg_catalog.pg_namespace s ON h.relnamespace=s.oid
JOIN pg_catalog.pg_class t ON h.reltoastrelid=t.oid
JOIN pg_catalog.pg_index i ON t.oid=i.indrelid
JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
WHERE s.nspname='information_schema'
ORDER BY h.relname"""


@cache
def contracts():
    path = TOOL / CATALOG_REVISION
    require(digest(path) == CATALOG_DIGEST, "postgres-stock-catalog-contract")
    overlay = json.loads(path.read_bytes())
    baseline = TOOL / overlay["base"]["path"]
    require(digest(baseline) == overlay["base"]["sha256"], "postgres-stock-catalog-baseline")
    return overlay["builds"], json.loads(baseline.read_bytes())["releases"]


def build(release, architecture):
    profiles, _ = contracts()
    return profiles[release.removeprefix("pg-")][architecture]["serverBuild"]


def layout(release, actual_build):
    version = release.removeprefix("pg-")
    builds, original = contracts()
    choices = builds[version]
    matches = [layout for layout in choices.values() if layout["serverBuild"] == actual_build]
    require(len(matches) == 1, "postgres-stock-catalog-unregistered-build", {"release": release, "server": actual_build})
    selected = matches[0]["informationSchemaToast"]
    previous = {row[5] for row in choices["amd64"]["informationSchemaToast"]}
    retained = [row for row in original[version] if row[0] != "pg_toast" or row[1] not in previous]
    require(len(previous) == len(selected) == 4 and len(retained) + len(selected) == len(original[version]),
            "postgres-stock-catalog-invalid-overlay")
    replacements = [["pg_toast", row[5], row[3], row[4], row[8] == "t", row[7], row[9] == "t", False]
                    for row in selected]
    return sorted([*retained, *replacements]), selected


def observe(db):
    return {"build": db.execute("SELECT pg_catalog.version()")[0][0], "informationSchemaToast": db.execute(TOAST_EDGES)}


def check(release, observation, identities):
    expected_catalog, expected_edges = layout(release, observation["build"])
    actual_edges = observation["informationSchemaToast"]
    same(len(expected_edges), len(actual_edges))
    for wanted, actual in zip(expected_edges, actual_edges):
        # REINDEX CONCURRENTLY can replace the index OID. The native edge and full census must still refer to the same object.
        same([*wanted[:6], *wanted[7:]], [*actual[:6], *actual[7:]])
        same([actual[4], actual[6]], identities.get(("pg_toast", actual[5])))
    return expected_catalog


def controls():
    verified = []
    for release in ("16.6", "17.2"):
        for architecture, alternate in (("amd64", "arm64"), ("arm64", "amd64")):
            version = build(release, architecture)
            inventory, edges = layout(release, version)
            contract = {"build": version, "informationSchemaToast": edges}
            identities = {("pg_toast", row[5]): [row[4], row[6]] for row in edges}
            same(inventory, check(release, contract, identities))
            crossed = {**contract, "build": build(release, alternate)}
            wrong_link = {**identities, ("pg_toast", edges[0][5]): [edges[0][4], "1"]}
            for witness, census in ((crossed, identities), (contract, wrong_link)):
                try:
                    check(release, witness, census)
                except Failure as failure:
                    same("meaning-mismatch", failure.signal)
                else:
                    raise Failure("postgres-stock-catalog-accepted-wrong-identity", [release, architecture])
            verified.append({"release": release, "architecture": architecture, "stockIndexes": len(inventory),
                             "wrongBuildRejected": True, "wrongIndexLinkRejected": True})
    return verified

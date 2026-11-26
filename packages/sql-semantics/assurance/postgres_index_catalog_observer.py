"""Observe which catalog objects a PostgreSQL index operation touched."""

import json
from platform import python_version
import sys

from postgres_index_catalog_cases import DATABASE, SCHEMAS, STATUS, declarations, rows, sql
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require

INDEXES = """SELECT ns.nspname, idx.relname, owner.relname, idx.relkind, info.indkey::text, am.amname,
                    info.indisunique, info.indisexclusion, info.indisprimary, info.indisvalid,
                    idx.oid::text, owner.oid::text, pg_catalog.pg_relation_filenode(idx.oid)::text
FROM pg_catalog.pg_index info
JOIN pg_catalog.pg_class idx ON idx.oid=info.indexrelid
JOIN pg_catalog.pg_class owner ON owner.oid=info.indrelid
JOIN pg_catalog.pg_am am ON am.oid=idx.relam
JOIN pg_catalog.pg_namespace ns ON ns.oid=idx.relnamespace
WHERE ns.nspname IN ('qual_front', 'qual_later')"""

ATTACHMENTS = """SELECT pn.nspname, p.relname, cn.nspname, c.relname
FROM pg_catalog.pg_inherits link
JOIN pg_catalog.pg_class p ON p.oid=link.inhparent
JOIN pg_catalog.pg_class c ON c.oid=link.inhrelid
JOIN pg_catalog.pg_namespace pn ON pn.oid=p.relnamespace
JOIN pg_catalog.pg_namespace cn ON cn.oid=c.relnamespace
WHERE p.relkind='I' AND pn.nspname IN ('qual_front', 'qual_later')"""


def insert(db, item, which, schema):
    values = ", ".join(f"({first}, {second})" for first, second in rows(item, which))
    name = qualified(schema, item["table"])
    db.execute(f"INSERT INTO {name} VALUES {values}")


def installed(db):
    entries, identity, allocation = {}, {}, {}
    for *shape, index, owner, file in db.execute(INDEXES):
        key = tuple(shape[:2])
        require(key not in entries, "postgres-index-catalog-duplicate-native-index", key)
        entries[key] = shape[:6] + [value == "t" for value in shape[6:]]
        identity[key] = (index, owner)
        allocation[key] = file
    return entries, identity, allocation


def observe(db, request):
    item, authored = request["trial"], request["sql"]
    allowed = {sql(item), *(sql(item, "schema-only", schema, canonical=True) for schema in SCHEMAS.values())}
    if authored not in allowed:
        return {"status": "outside-observer-scope"}
    db.execute("SET client_min_messages=warning; RESET search_path")
    for schema in SCHEMAS.values():
        db.execute(f"DROP SCHEMA IF EXISTS {quoted(schema)} CASCADE")
    db.execute("; ".join(declarations(item)))
    for which, schema in SCHEMAS.items():
        insert(db, item, which, schema)
    db.execute("SET search_path=" + ",".join(map(quoted, SCHEMAS.values())) + ",pg_catalog; SET client_min_messages=notice")
    prior, identities, files = installed(db)
    linked = sorted(db.execute(ATTACHMENTS))
    outcome = db.run(authored)
    final, final_ids, final_files = installed(db)
    stable = prior.keys() & final.keys()
    wrong = {key for shape, location in ((prior, files), (final, final_files))
             for key, row in shape.items() if (location[key] is None) != (row[3] == "I")}
    selected_data = {}
    for schema in SCHEMAS.values():
        all_rows = db.execute(f"SELECT id,payload FROM {qualified(schema, item['table'])} ORDER BY id, payload")
        selected_data[schema] = [[int(value) for value in row] for row in all_rows]
    return {"status": STATUS, "command": outcome["command"], "sqlstate": outcome["sqlState"], "notices": outcome["notices"],
            "before": sorted(prior.values()), "after": sorted(final.values()),
            "attachmentsBefore": linked, "attachmentsAfter": sorted(db.execute(ATTACHMENTS)),
            "changedFiles": sorted(list(key) for key in stable if files[key] != final_files[key]),
            "changedIds": sorted(list(key) for key in stable if identities[key] != final_ids[key]),
            "wrongFileRole": sorted(map(list, wrong)), "rows": selected_data}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "postgres-index-catalog-disposable-database")
        print(json.dumps({"database": settings, "currentDatabase": current, "python": python_version(),
                          "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            item = json.loads(line)
            result = [observe(db, request) for request in item] if isinstance(item, list) else observe(db, item)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()

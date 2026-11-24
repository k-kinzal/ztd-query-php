"""Measure PostgreSQL's named partitioned index lineage and independent owner storage."""

import json
from platform import python_version
import sys

from postgres_libpq import LIB, Session
from postgres_reindex_cases import FORMS, qualified
from postgres_reindex_partition_index_cases import STATUS, analyzed, fixture
from postgres_reindex_partition_table_observer import initialize, phases


def admitted(item, sql):
    schema, name, _ = item["reindex"]["target"]
    return sql in {
        analyzed(item)["sql"],
        "REINDEX INDEX " + qualified("fixture", "low_id_idx"),
        "REINDEX TABLE " + qualified("fixture", item["root"]),
        *(pattern + qualified(schema, name) for pattern in FORMS.values()),
    }


def observe(db, request):
    item, sql = request["trial"], request["sql"]
    if not admitted(item, sql):
        return {"status": "outside-observer-scope"}
    initialize(db, item, fixture(item, populate=True))
    return phases(db, item, sql, STATUS)


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in
                    ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            incoming = json.loads(line)
            result = [observe(db, item) for item in incoming] if isinstance(incoming, list) else observe(db, incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()

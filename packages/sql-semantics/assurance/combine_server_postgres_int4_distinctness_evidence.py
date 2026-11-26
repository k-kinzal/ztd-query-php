"""Replay both pinned PostgreSQL null-safe predicate datasets."""

import run_server_postgres_int4_distinctness
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_int4_distinctness)

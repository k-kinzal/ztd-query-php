"""Replay the searched int4 CASE on both pinned PostgreSQL releases."""

import run_server_postgres_int4_case
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_int4_case)

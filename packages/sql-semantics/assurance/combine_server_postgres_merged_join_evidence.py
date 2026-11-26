"""Replay PostgreSQL native merged join evidence for both registered releases."""

import run_server_postgres_merged_join
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_merged_join)

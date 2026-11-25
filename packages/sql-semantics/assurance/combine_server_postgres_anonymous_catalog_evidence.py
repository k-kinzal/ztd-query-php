"""Replay the anonymous PostgreSQL index witness from every required profile."""

import run_server_postgres_anonymous_catalog
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_anonymous_catalog)

"""Replay the exact PostgreSQL owner-wide rebuild release matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_table as domain


if __name__ == "__main__":
    main(domain)

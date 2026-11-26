"""Replay the pinned PostgreSQL application and system index rebuild matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_database as domain


if __name__ == "__main__":
    main(domain)

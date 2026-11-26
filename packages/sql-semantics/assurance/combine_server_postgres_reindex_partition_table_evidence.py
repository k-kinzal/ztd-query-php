"""Replay PostgreSQL partition table rebuild evidence through the fixed release matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_partition_table as domain


if __name__ == "__main__":
    main(domain)

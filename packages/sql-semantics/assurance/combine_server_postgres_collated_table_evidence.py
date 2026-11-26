"""Replay native collated-table observations on both required PostgreSQL profiles."""

from combine_server_join_evidence import main
import run_server_postgres_collated_table

if __name__ == "__main__":
    main(run_server_postgres_collated_table)

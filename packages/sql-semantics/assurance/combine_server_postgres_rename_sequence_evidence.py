"""Reconcile exact native sequence rename observations for the two releases."""

from combine_server_join_evidence import main
import run_server_postgres_rename_sequence

if __name__ == "__main__":
    main(run_server_postgres_rename_sequence)

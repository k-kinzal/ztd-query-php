"""Reconcile exact native owner-column dependency receipts across releases."""

from combine_server_join_evidence import main
import run_server_postgres_sequence_owner

if __name__ == "__main__":
    main(run_server_postgres_sequence_owner)

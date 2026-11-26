"""Replay the exact MySQL ALTER TABLE index removal evidence."""

from combine_server_join_evidence import main
import run_server_mysql_alter_index_removal

if __name__ == "__main__":
    main(run_server_mysql_alter_index_removal)

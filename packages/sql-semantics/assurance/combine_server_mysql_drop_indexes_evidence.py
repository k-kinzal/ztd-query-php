"""Replay the actual native MySQL atomic index-removal evidence."""

from combine_server_join_evidence import main
import run_server_mysql_drop_indexes

if __name__ == "__main__":
    main(run_server_mysql_drop_indexes)

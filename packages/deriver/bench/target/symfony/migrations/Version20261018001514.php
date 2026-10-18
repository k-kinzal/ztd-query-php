<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018001514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create communities, memberships, discussions, moderation, notifications and audit storage';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform, 'This service migration requires SQLite.');
        $this->addSql('CREATE TABLE audit_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, "action" VARCHAR(80) NOT NULL, subject VARCHAR(100) NOT NULL, details CLOB NOT NULL, actor_id INTEGER DEFAULT NULL, community_id INTEGER DEFAULT NULL, CONSTRAINT FK_3CDA66B510DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3CDA66B5FDA7B0BF FOREIGN KEY (community_id) REFERENCES communities (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX audit_retention ON audit_entries (created_at)');
        $this->addSql('CREATE INDEX IDX_3CDA66B510DAF24A ON audit_entries (actor_id)');
        $this->addSql('CREATE INDEX IDX_3CDA66B5FDA7B0BF ON audit_entries (community_id)');
        $this->addSql('CREATE TABLE boards (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, description CLOB NOT NULL, visibility VARCHAR(255) NOT NULL, requires_review BOOLEAN NOT NULL, archived_at DATETIME DEFAULT NULL, community_id INTEGER NOT NULL, CONSTRAINT FK_F3EE4D13FDA7B0BF FOREIGN KEY (community_id) REFERENCES communities (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX board_slug_unique ON boards (community_id, slug)');
        $this->addSql('CREATE INDEX IDX_F3EE4D13FDA7B0BF ON boards (community_id)');
        $this->addSql('CREATE TABLE communities (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, description CLOB NOT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_ECE312B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX community_slug_unique ON communities (slug)');
        $this->addSql('CREATE INDEX IDX_ECE312B7E3C61F9 ON communities (owner_id)');
        $this->addSql('CREATE TABLE digest_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, period VARCHAR(10) NOT NULL, sent_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_BBC0C7A5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX digest_delivery_unique ON digest_deliveries (user_id, period)');
        $this->addSql('CREATE INDEX IDX_BBC0C7A5A76ED395 ON digest_deliveries (user_id)');
        $this->addSql('CREATE TABLE memberships (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, role VARCHAR(255) NOT NULL, community_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_865A4776FDA7B0BF FOREIGN KEY (community_id) REFERENCES communities (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_865A4776A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX membership_unique ON memberships (community_id, user_id)');
        $this->addSql('CREATE INDEX IDX_865A4776FDA7B0BF ON memberships (community_id)');
        $this->addSql('CREATE INDEX IDX_865A4776A76ED395 ON memberships (user_id)');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, event_key VARCHAR(100) NOT NULL, message VARCHAR(255) NOT NULL, read_at DATETIME DEFAULT NULL, topic_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_6000B0D31F55203D FOREIGN KEY (topic_id) REFERENCES topics (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX notification_unique ON notifications (user_id, event_key)');
        $this->addSql('CREATE INDEX IDX_6000B0D31F55203D ON notifications (topic_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE TABLE post_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, body CLOB NOT NULL, reason VARCHAR(500) NOT NULL, post_id INTEGER NOT NULL, editor_id INTEGER NOT NULL, CONSTRAINT FK_28DF5C724B89032C FOREIGN KEY (post_id) REFERENCES posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_28DF5C726995AC4C FOREIGN KEY (editor_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_28DF5C724B89032C ON post_revisions (post_id)');
        $this->addSql('CREATE INDEX IDX_28DF5C726995AC4C ON post_revisions (editor_id)');
        $this->addSql('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, body CLOB NOT NULL, version INTEGER DEFAULT 1 NOT NULL, hidden_at DATETIME DEFAULT NULL, topic_id INTEGER NOT NULL, author_id INTEGER NOT NULL, CONSTRAINT FK_885DBAFA1F55203D FOREIGN KEY (topic_id) REFERENCES topics (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_885DBAFAF675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX post_thread ON posts (topic_id, id)');
        $this->addSql('CREATE INDEX IDX_885DBAFA1F55203D ON posts (topic_id)');
        $this->addSql('CREATE INDEX IDX_885DBAFAF675F31B ON posts (author_id)');
        $this->addSql('CREATE TABLE publication_receipts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, request_key VARCHAR(128) NOT NULL, fingerprint VARCHAR(64) NOT NULL, user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL, CONSTRAINT FK_328C16F3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_328C16F31F55203D FOREIGN KEY (topic_id) REFERENCES topics (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX publication_key_unique ON publication_receipts (user_id, request_key)');
        $this->addSql('CREATE INDEX IDX_328C16F3A76ED395 ON publication_receipts (user_id)');
        $this->addSql('CREATE INDEX IDX_328C16F31F55203D ON publication_receipts (topic_id)');
        $this->addSql('CREATE TABLE reactions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, kind VARCHAR(20) NOT NULL, post_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_38737FB34B89032C FOREIGN KEY (post_id) REFERENCES posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_38737FB3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX reaction_unique ON reactions (post_id, user_id)');
        $this->addSql('CREATE INDEX IDX_38737FB34B89032C ON reactions (post_id)');
        $this->addSql('CREATE INDEX IDX_38737FB3A76ED395 ON reactions (user_id)');
        $this->addSql('CREATE TABLE reports (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, category VARCHAR(30) NOT NULL, reason CLOB NOT NULL, status VARCHAR(255) NOT NULL, resolution CLOB DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, escalated_at DATETIME DEFAULT NULL, post_id INTEGER NOT NULL, reporter_id INTEGER NOT NULL, moderator_id INTEGER DEFAULT NULL, CONSTRAINT FK_F11FA7454B89032C FOREIGN KEY (post_id) REFERENCES posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F11FA745E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F11FA745D0AFA354 FOREIGN KEY (moderator_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX report_unique ON reports (post_id, reporter_id)');
        $this->addSql('CREATE INDEX IDX_F11FA7454B89032C ON reports (post_id)');
        $this->addSql('CREATE INDEX IDX_F11FA745E1CFE6F5 ON reports (reporter_id)');
        $this->addSql('CREATE INDEX IDX_F11FA745D0AFA354 ON reports (moderator_id)');
        $this->addSql('CREATE TABLE sanctions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, reason CLOB NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, community_id INTEGER NOT NULL, user_id INTEGER NOT NULL, moderator_id INTEGER NOT NULL, CONSTRAINT FK_5D0A15E3FDA7B0BF FOREIGN KEY (community_id) REFERENCES communities (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5D0A15E3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5D0A15E3D0AFA354 FOREIGN KEY (moderator_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX sanction_expiry ON sanctions (community_id, user_id, expires_at)');
        $this->addSql('CREATE INDEX IDX_5D0A15E3FDA7B0BF ON sanctions (community_id)');
        $this->addSql('CREATE INDEX IDX_5D0A15E3A76ED395 ON sanctions (user_id)');
        $this->addSql('CREATE INDEX IDX_5D0A15E3D0AFA354 ON sanctions (moderator_id)');
        $this->addSql('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, topic_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_4778A011F55203D FOREIGN KEY (topic_id) REFERENCES topics (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX subscription_unique ON subscriptions (topic_id, user_id)');
        $this->addSql('CREATE INDEX IDX_4778A011F55203D ON subscriptions (topic_id)');
        $this->addSql('CREATE INDEX IDX_4778A01A76ED395 ON subscriptions (user_id)');
        $this->addSql('CREATE TABLE topics (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, title VARCHAR(200) NOT NULL, status VARCHAR(255) NOT NULL, version INTEGER DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, board_id INTEGER NOT NULL, author_id INTEGER NOT NULL, CONSTRAINT FK_91F64639E7EC5785 FOREIGN KEY (board_id) REFERENCES boards (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_91F64639F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX topic_activity ON topics (board_id, status, updated_at)');
        $this->addSql('CREATE INDEX IDX_91F64639E7EC5785 ON topics (board_id)');
        $this->addSql('CREATE INDEX IDX_91F64639F675F31B ON topics (author_id)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(254) NOT NULL, display_name VARCHAR(80) NOT NULL, password VARCHAR(255) NOT NULL, verified_at DATETIME DEFAULT NULL, suspended_until DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, digest_enabled BOOLEAN NOT NULL, verification_hash VARCHAR(64) DEFAULT NULL, verification_expires_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX user_email_unique ON users (email)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_entries');
        $this->addSql('DROP TABLE digest_deliveries');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE post_revisions');
        $this->addSql('DROP TABLE publication_receipts');
        $this->addSql('DROP TABLE reactions');
        $this->addSql('DROP TABLE reports');
        $this->addSql('DROP TABLE sanctions');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE posts');
        $this->addSql('DROP TABLE topics');
        $this->addSql('DROP TABLE boards');
        $this->addSql('DROP TABLE communities');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

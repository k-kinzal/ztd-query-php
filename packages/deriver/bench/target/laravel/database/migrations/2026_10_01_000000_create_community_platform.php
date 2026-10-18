<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_until')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description');
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamps();
        });
        Schema::create('community_user', function (Blueprint $table): void {
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->primary(['community_id','user_id']);
        });
        Schema::create('boards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->text('description');
            $table->string('visibility', 20)->default('public');
            $table->boolean('requires_review')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['community_id','slug']);
        });
        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->string('title', 200);
            $table->string('state', 20);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_posted_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['board_id','state','last_posted_at']);
        });
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['topic_id','id']);
        });
        Schema::create('post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('editor_id')->constrained('users');
            $table->text('body');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('reporter_id')->constrained('users');
            $table->string('category', 20);
            $table->text('reason');
            $table->string('state', 20)->default('open');
            $table->foreignId('moderator_id')->nullable()->constrained('users');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
            $table->unique(['post_id','reporter_id']);
            $table->index(['state','created_at']);
        });
        Schema::create('bans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('moderator_id')->constrained('users');
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['community_id','user_id','expires_at']);
        });
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });
        Schema::create('tag_topic', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id','topic_id']);
        });
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['topic_id','user_id']);
        });
        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('kind', 20);
            $table->timestamps();
            $table->unique(['post_id','user_id']);
        });
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamp('purge_requested_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('community_id')->nullable()->constrained();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('context');
            $table->timestamps();
            $table->index(['community_id','created_at']);
        });
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('type', 40);
            $table->json('payload');
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('publication_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('key', 128);
            $table->string('fingerprint', 64);
            $table->foreignId('topic_id')->constrained();
            $table->timestamps();
            $table->unique(['user_id','key']);
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications','publication_receipts','outbox_events','audit_entries','attachments','reactions','subscriptions','tag_topic','tags','bans','reports','post_revisions','posts','topics','boards','community_user','communities','personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('suspended_until'));
    }
};

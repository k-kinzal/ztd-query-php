<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= $escape($topic['title']) ?> · Harbor</title><link rel="stylesheet" href="/style.css"></head>
<body><nav><a href="/">Harbor</a></nav><h1><?= $escape($topic['title']) ?></h1><p>Status: <?= $escape($topic['state']) ?></p>
<?php foreach ($topic['posts'] as $post): ?><article id="post-<?= $escape($post['id']) ?>"><strong><?= $escape($post['author']) ?></strong><small> · <?= $escape(gmdate('Y-m-d H:i', $post['created_at'])) ?> UTC</small><pre><?= $escape($post['body']) ?></pre></article><?php endforeach ?>
<?php if (count($topic['posts']) === 100): ?><a href="?after=<?= $escape(end($topic['posts'])['id']) ?>">Next replies</a><?php endif ?>
</body></html>

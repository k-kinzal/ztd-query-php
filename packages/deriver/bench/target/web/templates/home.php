<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Harbor communities</title><link rel="stylesheet" href="/style.css"></head>
<body><nav><strong>Harbor</strong><a href="/api/communities">Community API</a></nav><h1>Community directory</h1>
<?php foreach ($communities as $community): ?>
<section><h2><?= $escape($community['name']) ?></h2><p><?= $escape($community['description']) ?></p><a href="/api/communities/<?= $escape($community['id']) ?>/boards">Browse boards</a></section>
<?php endforeach ?>
<p>The authenticated API supports community administration, discussions, reports and personal data access. See the service documentation for client integration.</p>
</body></html>

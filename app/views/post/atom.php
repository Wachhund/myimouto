<?php
$host = (string) CONFIG()->server_host;
if (!preg_match('#^https?://#i', $host)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $base_url = $scheme . rtrim($host, '/');
} else {
    $base_url = rtrim($host, '/');
}

$to_absolute_url = static function ($url) use ($base_url) {
    $url = (string) $url;
    if ($url === '') {
        return $base_url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if ($url[0] !== '/') {
        $url = '/' . $url;
    }
    return $base_url . $url;
};

$to_atom_date = static function ($date) {
    $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if (!$ts) {
        $ts = time();
    }
    return gmdate(DATE_ATOM, $ts);
};
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= $this->h(CONFIG()->app_name) ?></title>
  <link href="<?= $this->h($base_url . '/post/atom') ?>" rel="self"/>
  <link href="<?= $this->h($base_url . '/post/index') ?>" rel="alternate"/>
  <id><?= $this->h($base_url . '/post/atom?tags=' . urlencode((string) $this->params()->tags)) ?></id>
  <?php if (!empty($this->posts) && isset($this->posts[0])) : ?>
    <updated><?= $this->h($to_atom_date($this->posts[0]->created_at)) ?></updated>
  <?php endif ?>
  <author><name><?= $this->h(CONFIG()->app_name) ?></name></author>
  <?php foreach ($this->posts as $post) : ?>
    <?php $post_url = $to_absolute_url('/post/show/' . $post->id); ?>
    <?php $updated_at = (Post::isAttribute('updated_at') && $post->updated_at) ? $post->updated_at : $post->created_at; ?>
    <?php $preview_url = method_exists($post, 'preview_url') ? $post->preview_url() : ''; ?>
    <entry>
      <title><?= $this->h($post->cached_tags) ?></title>
      <link href="<?= $this->h($post_url) ?>" rel="alternate"/>
      <?php if (!empty($post->source) && preg_match('#^https?://#i', $post->source)) : ?>
        <link href="<?= $this->h($post->source) ?>" rel="related"/>
      <?php endif ?>
      <id><?= $this->h($post_url) ?></id>
      <updated><?= $this->h($to_atom_date($updated_at)) ?></updated>
      <summary><?= $this->h($post->cached_tags) ?></summary>
      <content type="xhtml">
        <div xmlns="http://www.w3.org/1999/xhtml">
          <a href="<?= $this->h($post_url) ?>">
            <img src="<?= $this->h($to_absolute_url($preview_url)) ?>" alt="<?= $this->h($post->cached_tags) ?>"/>
          </a>
        </div>
      </content>
      <author>
        <name><?= $this->h($post->author() . ' (' . $post->width . 'x' . $post->height . ')') ?></name>
      </author>
    </entry>
  <?php endforeach ?>
</feed>

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

$mime_for = static function ($url) {
    $ext = strtolower((string) pathinfo(parse_url((string) $url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'png':
            return 'image/png';
        case 'gif':
            return 'image/gif';
        case 'webp':
            return 'image/webp';
        default:
            return 'application/octet-stream';
    }
};

$current_page = method_exists($this->posts, 'currentPage') ? $this->posts->currentPage() : 1;
$previous_page = method_exists($this->posts, 'previousPage') ? $this->posts->previousPage() : null;
$next_page = method_exists($this->posts, 'nextPage') ? $this->posts->nextPage() : null;
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $this->h(CONFIG()->app_name . '/' . (string) $this->params()->tags) ?></title>
  <link><?= $this->h($base_url . '/') ?></link>
  <atom:link rel="self" href="<?= $this->h($this->urlFor(['post#piclens', 'only_path' => false, 'tags' => $this->params()->tags, 'page' => $current_page])) ?>" />
  <description><?= $this->h(CONFIG()->app_name . ' PicLens RSS Feed') ?></description>
  <?php if ($previous_page) : ?>
    <atom:link rel="previous" href="<?= $this->h($this->urlFor(['post#piclens', 'only_path' => false, 'page' => $previous_page, 'tags' => $this->params()->tags])) ?>" />
  <?php endif ?>
  <?php if ($next_page) : ?>
    <atom:link rel="next" href="<?= $this->h($this->urlFor(['post#piclens', 'only_path' => false, 'page' => $next_page, 'tags' => $this->params()->tags])) ?>" />
  <?php endif ?>

  <?php foreach ($this->posts as $post) : ?>
    <?php $post_url = $to_absolute_url('/post/show/' . $post->id); ?>
    <?php $content_url = CONFIG()->image_samples ? $post->sample_url : $post->file_url; ?>
    <item>
      <title><?= $this->h($post->cached_tags) ?></title>
      <link><?= $this->h($post_url) ?></link>
      <guid><?= $this->h($post_url) ?></guid>
      <description><![CDATA[<img src="<?= $this->h($to_absolute_url($post->preview_url)) ?>" alt="<?= $this->h($post->cached_tags) ?>" />]]></description>
      <media:thumbnail url="<?= $this->h($to_absolute_url($post->preview_url)) ?>"/>
      <media:content url="<?= $this->h($to_absolute_url($content_url)) ?>" type="<?= $this->h($mime_for($content_url)) ?>"/>
    </item>
  <?php endforeach ?>
</channel>
</rss>

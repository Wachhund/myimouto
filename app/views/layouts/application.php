<!DOCTYPE html>
<html lang="<?= Rails::application()->I18n()->locale() ?: 'en' ?>" style="color-scheme: dark" class="action-<?= $this->request()->controller() ?> action-<?= $this->request()->controller() ?>-<?= $this->request()->action() ?> hide-advanced-editing">
<head>
<?php if ($this->params()->tags && preg_match('/(source:|fav:|date:|rating:|mpixels:|parent:|sub:|vote:|score:|order:|user:|limit:|holds:|pool:|[ \-])/')) : ?>
<meta name="robots" content="none">
<?php endif ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#222">
  <title><?= $this->html_title() ?></title>
  <meta name="description" content="yande.re - A Danbooru focusing on High Resolution Anime Scans, Ecchi Scans, Hentai Scans, Moe Scans, and Bishoujo Scans; unlimited downloads. ">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <link rel="top" title="<?= CONFIG()->app_name ?>" href="/">
  <?php
    $image_host = parse_url(CONFIG()->url_base, PHP_URL_HOST);
    $page_host  = $this->request()->host();
    if ($image_host && $image_host !== $page_host) {
        echo '  <link rel="preconnect" href="' . rtrim(CONFIG()->url_base, '/') . "\">\n";
    }
  ?>
  <?= $this->tag('link', ['rel' => 'canonical', 'href' => !empty($this->canonical_url) ? $this->canonical_url : $this->urlFor(array_merge($this->params()->toArray(), ['only_path' => false]))]) ?>
  <?php # The javascript-hide class is used to hide elements (eg. blacklisted posts) from JavaScript. ?>
  <script type="text/javascript">
    var css = ".javascript-hide { display: none !important; }";
    var style = document.createElement("style"); style.type = "text/css";
    if(style.styleSheet) // IE
      style.styleSheet.cssText = css;
    else
      style.appendChild(document.createTextNode(css));
    document.getElementsByTagName("head")[0].appendChild(style);
  </script>

  <?= $this->content('html_header') ?>
  <?= $this->autoDiscoveryLinkTag('atom', 'post#atom', array('tags' => $this->h($this->params()->tags))) ?> 
  <?php
  foreach (CONFIG()->asset_stylesheets as $asset) :
    echo $this->stylesheetLinkTag($asset);
  endforeach;
  foreach (CONFIG()->asset_javascripts as $asset) :
    echo $this->javascriptIncludeTag($asset);
  endforeach;
  ?> 
  <?= $this->partial('layouts/locale') ?>
  <!--[if lt IE 8]>
  <script src="/IE8.js" type="text/javascript"></script>
  <![endif]-->
  <?php //tag :link, :rel => 'search', :type => Mime::OPENSEARCH, :href => opensearch_path(:xml), :title => CONFIG['app_name'] ?>
  <?= CONFIG()->custom_html_headers ?>
  <!--[if lt IE 7]>
    <style type="text/css">
      body div#post-view > div#right-col > div > div#note-container > div.note-body {
        overflow: visible;
      }
    </style>
    <script src="<?= $this->request()->protocol() ?>ie7-js.googlecode.com/svn/trunk/lib/IE7.js" type="text/javascript"></script>
  <![endif]-->
  <meta name="csrf-param" content="csrf_token">
  <meta name="csrf-token" content="<?= $this->h($this->csrf_token) ?>">
</head>
<body>
  <a href="#content" class="skip-link"><?= $this->t('skip_to_content', 'Skip to content') ?></a>
  <?= $this->partial('layouts/notice') ?>
  <?php if ($this->contentFor('content')) : ?>
    <?= $this->content('content') ?>
  <?php else: ?>
    <main id="content">
      <?= $this->content() ?>
    </main>
  <?php endif ?>
  <?= $this->content('post_cookie_javascripts') ?>
  <?php if (CONFIG()->ga_tracking_id) echo $this->partial('layouts/ga') ?>
</body>
</html>

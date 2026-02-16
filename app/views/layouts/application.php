<!DOCTYPE html>
<html class="action-<?= $this->request()->controller() ?> action-<?= $this->request()->controller() ?>-<?= $this->request()->action() ?> hide-advanced-editing">
<head>
<?php if ($this->params()->tags && preg_match('/(source:|fav:|date:|rating:|mpixels:|parent:|sub:|vote:|score:|order:|user:|limit:|holds:|pool:|[ \-])/')) : ?>
<meta name="robots" content="none">
<?php endif ?>
  <meta http-equiv="Content-type" content="text/html;charset=UTF-8">
  <title><?= $this->html_title() ?></title>
  <meta name="description" content="yande.re - A Danbooru focusing on High Resolution Anime Scans, Ecchi Scans, Hentai Scans, Moe Scans, and Bishoujo Scans; unlimited downloads. ">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <link rel="top" title="<?= CONFIG()->app_name ?>" href="/">
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
  <?php //csrf_meta_tag ?>
</head>
<body>
  <?= $this->partial('layouts/notice') ?>
  <?php if ($this->contentFor('content')) : ?>
    <?= $this->content('content') ?>
  <?php else: ?>
    <div id="content">
      <?= $this->content() ?>
    </div>
  <?php endif ?>
  <?= $this->content('post_cookie_javascripts') ?>
  <?php if (CONFIG()->ga_tracking_id) echo $this->partial('layouts/ga') ?>
</body>
</html>

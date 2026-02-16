<!DOCTYPE html>
<html class="action-<?= $this->request()->controller() ?> action-<?= $this->request()->controller() ?>-<?= $this->request()->action() ?> hide-advanced-editing">
<head>
  <meta http-equiv="Content-type" content="text/html;charset=UTF-8">
  <title><?= $this->page_title ?></title>
  <meta name="description" content="<?= CONFIG()->app_name ?>">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <link rel="top" title="<?= CONFIG()->app_name ?>" href="/">
  <?php foreach (CONFIG()->asset_stylesheets as $asset) : ?>
  <?= $this->stylesheetLinkTag($asset) ?>
  <?php endforeach ?>
  <?php foreach (CONFIG()->asset_javascripts as $asset) : ?>
  <?= $this->javascriptIncludeTag($asset) ?>
  <?php endforeach ?>
  <?= CONFIG()->custom_html_headers ?>
  <?= $this->content('html_header') ?>
</head>
<body>
  <?= $this->partial("layouts/notice") ?>
  <div id="content">
    <?= $this->content() ?>
  </div>
  <?= $this->content('post_cookie_javascripts') ?>
  <?php
  /*
  <script type="text/javascript">
    var _gaq = _gaq || [];
    _gaq.push(['_setAccount', 'UA-291955-10']);
    _gaq.push(['_trackPageview']);

    (function() {
      var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
      ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
      var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
    })();
  </script>
  */
  ?>
</body>
</html>

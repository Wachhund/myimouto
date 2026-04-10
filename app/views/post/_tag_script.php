<div id="edit-tag-script" style="display: none;" class="top-corner-float">
  <h5><?= $this->t('.title') ?></h5>
  <form onsubmit="return false;" action="">
    <?= $this->textFieldTag("tag-script", "", ['size' => '40', 'id' => 'tag-script']) ?>
  </form>
  <div style="margin-top: 0.25em;">
    <?= $this->linkToFunction($this->t('.confirm'), 'PostModeMenu.apply_tag_script_to_all_posts()') ?>
  </div>
</div>
<?= $this->contentFor('post_cookie_javascripts', function () { ?>
  <script type="text/javascript">
    TagScript.init($("tag-script"));
  </script>
<?php }) ?>

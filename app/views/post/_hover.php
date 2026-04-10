<div style="display: none;" class="post-hover-overlay" id="index-hover-overlay">
  <a href="#"><?= $this->imageTag('images/blank.gif', ['alt' => '']) ?></a>
</div>

<div style="display: none;" class="post-hover" id="index-hover-info">
  <div id="hover-top-line">
    <div style="float: right; margin-left: 0em;">
      <span id="hover-dimensions"></span>,
      <span id="hover-file-size"></span>
    </div>
    <div style="padding-right: 1em">
      <?= $this->t('.post_number') ?><span id="hover-post-id"></span>
    </div>
  </div>

  <div style="padding-bottom: 0.5em">
    <div style="float: right; margin-left: 0em;">
      <span id="hover-author"></span>
    </div>
    <div style="padding-right: 1em">
      <?= $this->t('.score') ?>: <span id="hover-score"></span>
      <?= $this->t('.rating') ?>: <span id="hover-rating"></span>
      <span id="hover-is-parent"><?= $this->t('.parent') ?></span>
      <span id="hover-is-child"><?= $this->t('.child') ?></span>
      <span id="hover-is-pending"><?= $this->t('.pending') ?></span>
      <span id="hover-not-shown"><?= $this->t('.hidden') ?></span>
    </div>
    <div>
      <span id="hover-is-flagged"><span class="flagged-text"><?= $this->t('.flagged.by_html') ?></span><?= $this->t('.flagged.text') ?><span id="hover-flagged-by"></span>: <span id="hover-flagged-reason">gar</span></span>
    </div>
  </div>
  <div>
    <span id="hover-tags">
      <?php
      $tags = [];
  foreach (array_unique(CONFIG()->tag_types) as $tag) {
      $tags[] = Tag::type_name_from_value($tag);
  }
  usort($tags, function ($a, $b) {
      return strcmp((string) Tag::tag_list_order($a), (string) Tag::tag_list_order($b));
  });
  foreach ($tags as $name) :
      ?>
        <span class="tag-type-<?= $name ?>"><a id="hover-tag-<?= $name ?>"></a></span>
      <?php endforeach ?>
    </span>
  </div>
</div>

<?= $this->contentFor('post_cookie_javascripts', function () { ?>
<script type="text/javascript">Post.hover_info_init();</script>
<?php }) ?>


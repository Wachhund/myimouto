<div id="blacklisted-sidebar" style="display: none;">
  <h5>
    <?= $this->linkToFunction($this->t('.hidden_posts'), "$('blacklisted-list-box').toggle()", ['class' => 'no-focus-outline']) ?>
    <span id="blacklist-count" class="post-count"></span>
  </h5>
  <div id="blacklisted-list-box" style="display: none; margin-bottom: 1em;">
    <ul id="blacklisted-list" style="margin-bottom: 0em;">
      <li>
    </ul>

    <?= $this->formTag("#", ['id' => 'blacklisted-tag-add', 'level' => 'member'], function () { ?>
      <div>
        » <?= $this->textFieldTag("add-blacklist", "", ['size' => '20']) ?>
        <?= $this->linkToFunction($this->t('.add'), "Post.blacklist_add_commit();", ['class' => 'text-button', 'style' => 'padding: 0px 4px', 'level' => 'blocked']) ?>
        <?= $this->submitTag($this->t('.add'), ['style' => 'display: none;']) ?>
      </div>
      <?= $this->t('.info') ?>
    <?php }) ?>
  </div>

</div>

<?= $this->contentFor('post_cookie_javascripts', function () { ?>
<script type="text/javascript">
  document.observe("dom:loaded", function() {
    $("blacklisted-tag-add").observe("submit", function(e) {
      if(e.stopped) return;
      e.stop();
      Post.blacklist_add_commit();
    });
  });
</script>
<?php }) ?>


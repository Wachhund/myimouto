<?php $this->provide('title', '/' . str_replace('_', ' ', $this->params()->tags)) ?>
<div id="post-list">
  <?php
    if ($this->tag_suggestions) :
      $total = count($this->tag_suggestions);
      $count = 0;
  ?>
    <div class="status-notice">
      <?= $this->t('.maybe_you_meant') ?>: <?= implode(', ', array_map(function($x)use($total, &$count){
          $count++;
          $or = $count == $total && $total > 1 ? 'or ' : '';
          return $or.$this->tag_link($x);
        }, $this->tag_suggestions)) ?> 
    </div>
  <?php
      unset($total, $count);
    endif
  ?>

  <aside class="sidebar">
    <?= $this->partial('search') ?>
    <?php if (current_user()->is_privileged_or_higher()) : ?>
    <div style="margin-bottom: 1em;" id="mode-box" class="advanced-editing">
      <h5><?= $this->t('.mode') ?></h5>
      <form onsubmit="return false;" action="">
        <div>
          <select name="mode" id="mode" onchange="PostModeMenu.change()" onkeyup="PostModeMenu.change()" style="width: 13em;">
            <option value="view"><?= $this->t('.mode_form.view') ?></option>
            <option value="edit"><?= $this->t('.mode_form.edit') ?></option>
<!--            <option value="rating-s">Rate safe</option>
            <option value="rating-q">Rate questionable</option>
            <option value="rating-e">Rate explicit</option>
            <?php if (current_user()->is_privileged_or_higher()) : ?>
              <option value="lock-rating">Lock rating</option>
              <option value="lock-note">Lock notes</option>
            <?php endif ?> -->
            <?php if (current_user()->is_mod_or_higher()) : ?>
              <option value="approve"><?= $this->t('.mode_form.approve') ?></option>
            <?php endif ?>
            <option value="flag"><?= $this->t('.mode_form.flag') ?></option>
            <option value="apply-tag-script"><?= $this->t('.mode_form.script') ?></option>
            <option value="reparent-quick"><?= $this->t('.mode_form.reparent') ?></option>
            <?php if ($this->searching_pool) : ?>
              <option value="remove-from-pool"><?= $this->t('.mode_form.pool_remove') ?></option>
            <?php endif ?>
            <?php if (CONFIG()->delete_post_mode && current_user()->is_admin()) : ?>
              <option value="destroy">Delete posts</option>
            <?php endif ?>
          </select>
        </div>
      </form>
    </div>

    <?= $this->partial('tag_script') ?>
    <?php endif ?>

    <?php if ($this->searching_pool) : ?>
      <?= $this->t(['.pool_view_html', 'pool' => $this->linkTo($this->h($this->searching_pool->pretty_name()), array('pool#show', 'id' => $this->searching_pool->id))]) ?>
    <?php endif ?>

    <?php if ($this->showing_holds_only) : ?>
      <?php if (!$this->posts->blank()) : ?>
        <div style="margin-bottom: .5em;">
          <?= $this->linkToFunction($this->t('.activate'), "Post.activate_all_posts()") ?>
        </div>
      <?php endif ?>
    <?php else: ?>
      <div id="held-posts" style="display: none; margin-bottom: .5em;"><?= $this->t(['.held_html', 'count' => $this->contentTag('span', '', ['id' => 'held-posts-count'])]) ?> (<a href="#"><?= $this->t('.held_view') ?></a>).</div>
    <?php endif ?>

    <?= $this->partial('blacklists') ?>
    <?= $this->partial('tags', array('include_tag_hover_highlight' => 'true')) ?>

    <br />

    <?php if (CONFIG()->can_show_ad('post#index-sidebar', current_user())) : ?>
    <?= $this->partial('vertical') ?>
    <?php endif ?>
  </aside>
  <div class="content">
    <?php if (!empty($this->ambiguous_tags)) : ?>
      <div class="status-notice">
        <?= $this->t('.ambiguous') ?>: <?= implode(', ', array_map(function($x){ return $this->linkTo($this->h($x), ['wiki#show', 'title' => $x]); }, $this->ambiguous_tags)) ?>
      </div>
    <?php endif ?>
    <?php if (CONFIG()->can_show_ad('post#index-top', current_user())) : ?>
      <?= $this->partial('horizontal', ['position' => 'top']) ?>
    <?php endif ?>

    <div id="quick-edit" style="display: none;" class="top-corner-float">
      <?= $this->formTag('#update', function(){ ?>
        <?= $this->textAreaTag("post[tags]", "", array('size' => '60x2', 'id' => 'post_tags')) ?>
        <?= $this->submitTag($this->t('buttons.update')) ?>
        <?= $this->tag('input', array('type' => 'button', 'value' => $this->t('buttons.cancel'), 'class' => 'cancel')) ?>
      <h4 style="float: right;"><?= $this->t('.edit_tags') ?></h4>
      <?php }) ?>
    </div>

    <?= $this->partial("hover") ?>
    <?= $this->partial('posts', array('posts' => $this->posts)) ?>

    <div id="paginator">
      <?= $this->willPaginate($this->posts) ?>
    </div>
    
    <?php if (CONFIG()->can_show_ad('post#index-bottom', current_user())) : ?>
      <?= $this->partial('horizontal', ['position' => 'bottom', 'center' => true]) ?>
    <?php endif ?>
  </div>
</div>

<?= $this->contentFor('post_cookie_javascripts', function() { ?>
<script type="text/javascript">
  post_quick_edit = new PostQuickEdit($("quick-edit"));

  PostModeMenu.init(<?= $this->searching_pool && $this->searching_pool->id ?>)
  <?php foreach ($this->preload as $post) : ?>
  Preload.preload('<?= $post->preview_url() ?>');
  <?php endforeach ?>

  var held_posts = Cookie.get("held_post_count");
  if(held_posts && held_posts > 0)
  {
    var e = $("held-posts");
    if(e)
    {
      var a = e.down("A");
      var cnt = e.down("#held-posts-count");
      cnt.update("" + held_posts + " " + (held_posts == 1? "post":"posts"));
      a.href = "/post/index?tags=holds%3Aonly%20user%3A" + Cookie.get("login") + "%20limit%3A100"
      e.show();
    }
  }
  Post.cache_sample_urls();
  <?= $this->tag_completion_box('$("post_tags")') ?>
  if($("tag-script")) {<?= $this->tag_completion_box('$("tag-script")') ?>}
</script>
<?php }) ?>

<?= $this->contentFor('html_header', function() { ?>
  <?= $this->auto_discovery_link_tag_with_id('rss', array('post#piclens', 'tags' => $this->params()->tags, 'page' => $this->params()->page), array('id' => 'pl')) ?> 
  <?= $this->navigation_links($this->posts) ?> 
<?php }) ?>

<?= $this->partial('footer') ?>

<?php if ($this->contentFor('subnavbar')) : ?>
  <!-- Align the links to the content, not the window. -->
  <div style="clear: both;">
    <div class="sidebar">&nbsp;</div>
    <div class="footer" style="clear: none;">
      <ul class="flat-list" id="subnavbar">
        <?= $this->content('subnavbar') ?>
      </ul>
    </div>
  </div>
  <?php $this->clear_content_for('subnavbar') ?>
<?php endif ?>

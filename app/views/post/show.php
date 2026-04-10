<?php $this->canonical_url = $this->urlFor(['post#show', 'id' => $this->post->id, 'only_path' => false]) ?>
<?php $this->provide('title', str_replace('_', ' ', $this->post->title_tags())) ?>
<?= $this->contentFor('html_header', function() { ?>
  <?= $this->partial('social_meta') ?>
<?php }) ?>
<div id="post-view">
  <?php if (!$this->post) : ?>
    <h2><?= $this->t('.empty') ?></h2>
  <?php else: ?>
    <?php if ($this->post->can_be_seen_by(current_user())) : ?>
      <script type="text/javascript">Post.register_resp(<?= json_encode(Post::batch_api_data(array($this->post))) ?>);</script>
    <?php endif ?>
    <script type="text/javascript">var FLAG_REASONS = <?= json_encode(CONFIG()->flag_reasons) ?>;</script>

    <?= $this->partial('post/show_partials/status_notices', array('pools' => $this->pools)) ?>

    <div class="sidebar">
      <?= $this->partial('search') ?>
      <?= $this->partial('tags') ?>
      <?= $this->partial('post/show_partials/statistics_panel') ?>
      <?= $this->partial('post/show_partials/options_panel') ?>
      <?= $this->partial('post/show_partials/related_posts_panel') ?>
 <br />
  <?php if (CONFIG()->can_show_ad('post#show-sidebar', current_user())) : ?>
    <?= $this->partial('vertical') ?>
  <?php endif ?>
    </div>
    <div class="content" id="right-col">
      <?php if (CONFIG()->can_show_ad('post#show-top', current_user())) : ?>
        <?= $this->partial('horizontal', ['position' => 'top']) ?>
      <?php endif ?>
      
      <?= $this->partial('post/show_partials/image') ?>
      <?= $this->partial('post/show_partials/image_footer', ['post_id' => $this->post->id]) ?>
      <?= $this->partial('post/show_partials/edit') ?>
      <?= $this->partial('post/show_partials/comments') ?>
      
      <?php if (CONFIG()->can_show_ad('post#show-bottom', current_user())) : ?>
        <?= $this->partial('horizontal', ['position' => 'bottom']) ?>
      <?php endif ?>
    </div>

    <?= $this->contentFor('post_cookie_javascripts', function() { ?> 
      <script type="text/javascript">
        RelatedTags.init(Cookie.get('my_tags'), '<?= $this->params()->url ?>')

        if (Cookie.get('resize_image') == '1') {
          Post.resize_image()
        }

        var anchored_to_comment = window.location.hash == "#comments";
        if(window.location.hash.match(/^#c[0-9]+$/))
          anchored_to_comment = true;
          
        if (Cookie.get('show_defaults_to_edit') == '1' && !anchored_to_comment) {
          $('comments').hide();
          $('edit').show();
        }

        <?php $browser_url = "/post/browse#".$this->post->id ?>
        <?php !empty($this->following_pool_post) && $browser_url .= "/pool:" . $this->following_pool_post->pool_id ?>
        OnKey(66, { AlwaysAllowOpera: true }, function(e) { window.location.href = <?= json_encode($browser_url) ?>; });
      </script>
    <?php }) ?>
  <?php endif ?>
</div>

<?php if (CONFIG()->post_show_hotkeys) : ?>
<script>(function($){$(document).keydown(function(ev){
var t = $(ev.target), k = ev.keyCode;
if ((k == 69 || k == 82) && (t.prop('tagName') != 'TEXTAREA' && (t.prop('tagName') != 'INPUT' || (t.attr('type') && t.attr('type') != 'text' && t.attr('type') != 'password')))) {
  e = k == 69 ? '.show_edit_form' : '.show_reply_form'; $(e).click(); return false
}})})(jQuery);</script>
<?php endif ?>

<?= $this->tag_completion_box('$("post_tags")', ['$("edit-form")', '$("post_tags")', '$("post_old_tags")'], true) ?>

<?= $this->partial('footer') ?>

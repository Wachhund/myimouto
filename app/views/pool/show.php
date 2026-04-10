<div id="pool-show">
  <h4><?= $this->t(['.title', 'name' => $this->h($this->pool->pretty_name())]) ?></h4>
  <?php if ($this->pool->description) : ?>
    <div style="margin-bottom: 2em;"><?= $this->format_text($this->pool->description) ?></div>
  <?php endif ?>
  <div style="margin-top: 1em;">
  <ul id="post-list-posts">
    <?php foreach ($this->posts as $post) : ?>
      <?= $this->print_preview($post, ['onclick' => "return remove_post_confirm(" . $post->id . ", " . $this->pool->id . ")",
          'user' => current_user(), 'display' => $this->browse_mode == 1 ? 'large' : 'block', 'hide_directlink' => $this->browse_mode == 1]) ?>
    <?php endforeach ?>
  </ul>
  </div>
</div>
<script type="text/javascript">
  function remove_post_confirm(post_id, pool_id) {
    if (!$("del-mode") || !$("del-mode").checked) {
      return true
    }

    Pool.remove_post(post_id, pool_id)
    return false
  }

  Post.register_resp(<?= json_encode(Post::batch_api_data($this->posts->members())) ?>);
</script>
<?= $this->partial("post/hover") ?>

<div id="paginator">
  <?= $this->willPaginate($this->posts, ['class' => "no-browser-link"]) ?>

  <div style="display: none;" id="info"><?= $this->t('.delete_mode_info') ?></div>
</div>

<?php $this->contentFor('footer', function () { ?>
  <?php if (CONFIG()->pool_zips && current_user()->can_see_posts()) : ?>
    <?php $zip_params = [] ?>
    <?php $has_jpeg = CONFIG()->jpeg_enable && $this->pool->has_jpeg_zip($zip_params) ?>
    <?php if ($has_jpeg) : ?>
      <li><?= $this->link_to_pool_zip($this->t('.links.jpeg'), $this->pool, array_merge($zip_params, ['jpeg' => true])) ?></li>
    <?php endif ?>
    <?php $li_class = $has_jpeg ? "advanced-editing" : "" ?>
    <li class="<?= $li_class ?>"><?= $this->link_to_pool_zip($this->t('.links.png'), $this->pool, $zip_params, ['has_jpeg' => $has_jpeg]) ?></li>
    <?php if (CONFIG()->allow_pool_zip_pretty_filenames) : ?>
    <li class="del-mode"><?= $this->checkBoxTag('pretty_filenames') ?> <label for="pretty_filenames">Pretty filenames</label></li>
    <?php endif ?>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('.links.index_view'), ['controller' => "post", 'action' => "index", 'tags' => "pool:" . $this->pool->id]) ?> </li>
  <?php if (!current_user()->is_anonymous()) : ?>
  <li><?= $this->linkToFunction($this->t('.links.toggle_view'), "User.set_pool_browse_mode(" . (1 - $this->browse_mode) . ");") ?></li>
  <?php endif ?>
  <?php if (current_user()->has_permission($this->pool)) : ?>
    <li><?= $this->linkTo($this->t('.links.edit'), ['action' => "update", 'id' => $this->params()->id]) ?></li>
    <li><?= $this->linkTo($this->t('.links.delete'), ['action' => "destroy", 'id' => $this->params()->id]) ?></li>
  <?php endif ?>
<?php }) ?>

<?php $this->contentFor('footer_final', function () { ?>
  <br />
  <?php if (current_user()->can_change($this->pool, 'posts')) : ?>
    <li><?= $this->linkTo($this->t('.links.order'), ['action' => "order", 'id' => $this->params()->id]) ?></li>
    <li><?= $this->linkTo($this->t('.links.import'), ['action' => "import", 'id' => $this->params()->id]) ?></li>
    <?php if (current_user()->is_contributor_or_higher()) : ?>
      <li><?= $this->linkTo($this->t('.links.copy'), ['action' => "copy", 'id' => $this->params()->id]) ?></li>
      <li><?= $this->linkTo($this->t('.links.transfer'), ['action' => "transfer_metadata", 'to' => $this->params()->id]) ?></li>
    <?php endif ?>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('.links.history'), ['controller' => "history", 'action' => "index", 'search' => "pool:" . $this->params()->id]) ?></li>
  <?php if (current_user()->can_change($this->pool, 'posts')) : ?>
  <li class="advanced-editing del-mode">
    <input type="checkbox" id="del-mode" onclick="Element.toggle('info')">
    <label for="del-mode"><?= $this->t('.links.delete_mode') ?></label>
  </li>
  <?php endif ?>
<?php }) ?>

<script>
jQuery(function(){
  var $ = jQuery
  $('#pretty_filenames').click(function(){
    if ($(this).is(':checked')) {
      $('.pool_zip_download').each(function(){
        var href = $(this).attr('href'), glue;
        href.match(/\?/) ? glue = '&' : glue = '?'
        $(this).attr('href', href + glue + 'pretty_filenames=1')
      })
    } else {
      $('.pool_zip_download').each(function(){
        var href = $(this).attr('href'), glue; 
        href.match(/&/) ? glue = '&' : glue = '?'
        if (href.match(/pretty_filenames/)) {
          href = href.replace(glue + 'pretty_filenames=1', '')
          $(this).attr('href', href)
        }
      })
    }
  })
})
</script>

<?= $this->partial("footer") ?>

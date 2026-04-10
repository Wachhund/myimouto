<div id="post-add">
  <div id="static_notice" style="display: none;"></div>

  <?php if ($this->deleted_posts > 0) : ?>
    <div id="posts-deleted-notice" class="has-deleted-posts" style="margin-bottom: 1em;">
      <?= $this->t([$this->deleted_posts == 1 ? '.posts_deleted.notice_singular_html' : '.posts_deleted.notice_plural_html',
          'recently_deleted' => $this->linkTo($this->t('.posts_deleted.recently'), ['#deleted_index', 'user_id' => current_user()->id])])
      ?>
      (<?= $this->linkToFunction($this->t('.posts_deleted.hide'), 'Post.acknowledge_new_deleted_posts();') ?>)
    </div>
  <?php endif ?>

  <?php if (!current_user()->is_privileged_or_higher()) : ?>
    <div style="margin-bottom: 2em;">
      <h4><?= $this->t('.guidelines.title') ?></h4>
      <p><?= $this->t('.guidelines.info') ?></p>
      <ul>
        <li><?= $this->t(['.guidelines.do_not.tags_html', 'tags' => substr_replace(($str = implode(', ', array_map(function ($t) {
            return $this->linkTo(str_replace('_', ' ', $t), ['wiki#show', 'title' => $t]);
        }, ['furry', 'yaoi', 'guro', 'toon', 'poorly_drawn']))), ' or', strrpos($str, ','), 1) ]) ?></li>
        <li><?= $this->t(['.guidelines.do_not.with_html', 'with' => $this->linkTo($this->t('.guidelines.do_not.compression_artifacts'), ['wiki#show', 'title' => 'compression_artifacts'])]) ?></li>
        <li><?= $this->t(['.guidelines.do_not.with_html', 'with' => $this->linkTo($this->t('.guidelines.do_not.obnoxious_watermarks'), ['wiki#show', 'title' => 'watermark'])]) ?></li>
        <li><?= $this->linkTo($this->t('.guidelines.group'), 'help#post_relationships') ?></li>
        <li><?= $this->t(['.guidelines.more_html', 'more_link' => $this->linkTo($this->t('.guidelines.more_link'), 'help#tags')]) ?></li>
      </ul>
      <p><?= $this->t(['.guidelines.limit', 'n' => ($count = CONFIG()->member_post_limit - Post::where("user_id = ? AND created_at > ?", current_user()->id, date('Y-m-d H:i:s', strtotime('-1 day')))->count()) == 1 ? $count . " post" : $count . " posts"]) ?></p>
    </div>
  <?php endif ?>

  <?= $this->formTag('post#create', ['level' => 'member', 'multipart' => true, 'id' => 'edit-form'], function () { ?>
    <div id="posts">
      <?php if ($this->params()->url) : ?>
        <?= $this->tag('img', ['src' => $this->params()->url, 'alt' => $this->params()->url, 'title' => 'Preview', 'id' => 'image']) ?>
        <p id="scale"></p>
        <script type="text/javascript">
        document.observe("dom:loaded", function() {
          if ($("image").height > 400) {
            var width = $("image").width
            var height = $("image").height
            var ratio = 400.0 / height
            $("image").width = width * ratio
            $("image").height = height * ratio
            $("scale").innerHTML = "Scaled " + parseInt(100 * ratio) + "%"
          }
        })
        </script>
      <?php endif ?>

      <table class="form">
        <tfoot>
          <tr>
            <td></td>
            <td>
              <?= $this->submitTag($this->t('.form.upload'), ['tabindex' => '8', 'accesskey' => 's', 'class' => 'submit', 'style' => 'margin: 0;']) ?>
              <?= $this->submitTag($this->t('buttons.cancel'), ['tabindex' => '8', 'accesskey' => 's', 'class' => 'cancel', 'style' => 'display: none; vertical-align: bottom; margin: 0;']) ?>
              <div id="progress" class="upload-progress-bar" style="display: none;">
                <div class="upload-progress-bar-fill"></div>
              </div>
              <span style="display: none;" id="post-exists"><?= $this->t('.already_exists') ?>: <a href="#" id="post-exists-link"></a></span>
              <span style="display: none;" id="post-upload-error"></span>
            </td>
          </tr>
        </tfoot>
        <tbody>
          <tr>
            <th width="15%"><label for="post_file"><?= $this->t('.form.file') ?></label></th>
            <td width="85%"><?= $this->fileField("post", "file", ['size' => '50', 'tabindex' => '1']) ?><span class="similar-results" style="display: none;"></span></td>
          </tr>
          <tr>
            <th>
              <label for="post_source"><?= $this->t('.form.source._') ?></label>
              <?php if (!current_user()->is_privileged_or_higher()) : ?>
                <p><?= $this->t('.form.source.info') ?></p>
              <?php endif ?>
            </th>
            <td>
              <?= $this->textField("post", "source", ['value' => $this->params()->url, 'size' => '50', 'tabindex' => '2']) ?>
              <?php if (CONFIG()->enable_artists) : ?>
                <?= $this->linkToFunction($this->t('.form.find_artist'), "RelatedTags.find_artist(\$F('post_source'))") ?>
              <?php endif ?>
            </td>
          </tr>
          <tr>
            <th>
              <label for="post_tags"><?= $this->t('.form.tags._') ?></label>
              <?php if (!current_user()->is_privileged_or_higher()) : ?>
                <p><?= $this->t('.form.tags.info') ?>(<?= $this->linkTo($this->t('.form.help'), ['help#tags'], ['target' => '_blank']) ?>)</p>
              <?php endif ?>
            </th>
            <td>
              <?= $this->textArea("post", "tags", ['value' => $this->params()->tags, 'size' => '60x2', 'tabindex' => '3']) ?>
              <?= $this->linkToFunction($this->t('.form.find_related.tags'), "RelatedTags.find('post_tags')") ?> |
              <?= $this->linkToFunction($this->t('.form.find_related.artists'), "RelatedTags.find('post_tags', 'artist')") ?> |
              <?= $this->linkToFunction($this->t('.form.find_related.characters'), "RelatedTags.find('post_tags', 'char')") ?> |
              <?= $this->linkToFunction($this->t('.form.find_related.copyrights'), "RelatedTags.find('post_tags', 'copyright')") ?> |
              <?= $this->linkToFunction($this->t('.form.find_related.circles'), "RelatedTags.find('post_tags', 'circle')") ?>
            </td>
          </tr>
          <?php if (CONFIG()->enable_parent_posts) : ?>
            <tr>
              <th><label for="post_parent_id"><?= $this->t('.form.parent') ?></label></th>
              <td><?= $this->textField("post", "parent_id", ['value' => $this->params()->parent, 'size' => '5', 'tabindex' => '4']) ?></td>
            </tr>
          <?php endif ?>
          <tr>
            <th>
              <label for="post_rating_questionable"><?= $this->t('.form.rating._') ?></label>
              <?php if (!current_user()->is_privileged_or_higher()) : ?>
                <p><?= $this->t('.form.rating.info') ?>(<?= $this->linkTo($this->t('.form.help'), ['help#ratings'], ['target' => '_blank']) ?>)</p>
              <?php endif ?>
            </th>
            <td>
              <input id="post_rating_explicit" name="post[rating]" type="radio" value="e" <?php if (($this->params()->rating ?: $this->default_rating) == "e") : ?>checked="checked"<?php endif ?> tabindex="5">
              <label for="post_rating_explicit"><?= $this->t('ratings.e') ?></label>

              <input id="post_rating_questionable" name="post[rating]" type="radio" value="q" <?php if (($this->params()->rating ?: $this->default_rating) == "q") : ?>checked="checked"<?php endif ?> tabindex="6">
              <label for="post_rating_questionable"><?= $this->t('ratings.q') ?></label>

              <input id="post_rating_safe" name="post[rating]" type="radio" value="s" <?php if (($this->params()->rating ?: $this->default_rating) == "s") : ?>checked="checked"<?php endif ?> tabindex="7">
              <label for="post_rating_safe"><?= $this->t('ratings.s') ?></label>
            </td>
          </tr>
          <?php if (current_user()->is_contributor_or_higher()) : ?>
            <tr>
              <th><label for="anonymous"><?= $this->t('.anonymous') ?></label></th>
              <td><?= $this->checkBoxTag('anonymous', '1') ?></td>
            </tr>
          <?php endif ?>
        </tbody>
      </table>

      <div id="related"><em><?= $this->t('.form.find_related.none') ?></em></div>
    </div>
  <?php }) ?>

</div>

<script type="text/javascript">
  Post.observe_text_area("post_tags")
  if (Cookie.get("upload-disclaimer") == "1") {
    $("upload-disclaimer").hide()
  }

  /* Set up PostUploadForm in dom:loaded, to make sure the login handler can attach to
   * the form first. */
  document.observe("dom:loaded", function() {
    var form = $("edit-form");
    form.down("#post_file").on("change", function(e) { form.down("#post_tags").focus(); });

    if(form)
    {
      new PostUploadForm(form, $("progress"));
      new UploadSimilarSearch(form.down("#post_file"), form.down(".similar-results"));
    }
  }.bindAsEventListener());
  
  jQuery(function(){
    var $ = jQuery;
    $('#edit-form').submit(function(){
      if (!$('#post_file').val() && !$('#post_source').val()) {
        notice("Select a file or enter a source");
        return false;
      }
    })
  });
</script>

<?= $this->contentFor('post_cookie_javascripts', function () { ?>
  <script type="text/javascript">
    RelatedTags.init(Cookie.unescape(Cookie.get('my_tags')), '<?= $this->params()->ref ?: $this->params()->url ?>')
  </script>
<?php }) ?>

<?= $this->partial("footer") ?>

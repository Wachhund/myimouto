<div id="post-list">
    <div class="sidebar">
      <?= $this->partial("search") ?>
      <?php if (CONFIG()->can_see_ads(current_user())) : ?>
        <?= CONFIG()->ad_code_index_side ?>
      <?php endif ?>
      <div style="margin-bottom: 1em;" id="mode-box">
        <h5><?= $this->t('.mode') ?></h5>
        <form onsubmit="return false;" action="">
          <div>
            <select name="mode" id="mode" onchange="PostModeMenu.change()" onkeyup="PostModeMenu.change()" style="width: 13em;">
              <option value="view"><?= $this->t('.view_posts') ?></option>
              <option value="reparent"><?= $this->t('.reparent') ?></option>
              <option value="dupe"><?= $this->t('.flag_duplicate') ?></option>
              <option value="edit"><?= $this->t('.edit_posts') ?></option>
<!--              <option value="rating-s">Rate Safe</option>
              <option value="rating-q">Rate Questionable</option>
              <option value="rating-e">Rate Explicit</option>
              <?php if (current_user()->is_privileged_or_higher()) : ?>?>
                <option value="lock-rating">Lock Rating</option>
                <option value="lock-note">Lock Notes</option>
              <?php endif ?>-->
              <option value="flag"><?= $this->t('.flag_post') ?></option>
              <option value="apply-tag-script"><?= $this->t('.apply_tag_script') ?></option>
            </select>
          </div>
        </form>
      </div>

      <?= $this->partial("tag_script") ?>
      <?= $this->partial("blacklists") ?>

      <div>
        <h5><?= $this->t('.services') ?></h5>
        <ul>
          <li> <?= $this->linkTo($this->t('.use_all_services'), array_merge(['post#similar'], $this->params()->get(), ['services' => 'all'])) ?>
          <?php foreach (CONFIG()->image_service_list as $service => $server) : ?>
          <li>
            <span class="service-link<?php if (in_array($service, $this->services)) {
                echo " service-active";
            } ?>">
              <?= $this->imageTag($this->get_service_icon($service), ['class' => "service-icon", 'id' => "list"]) ?>
              <?= $this->linkTo($service, array_merge($this->params()->get(), ['controller' => 'post', 'action' => 'similar', 'services' => $service])) ?>
              <?php if ($this->errors and !empty($this->errors['server']['message'])) : ?>
                (<?= $this->t('.down') ?>)
                <!-- <?= $this->errors['server']['message'] ?> -->
              <?php endif ?>
            </span>
          <?php endforeach ?>
        </ul>
      </div>
      <div>
        <h5><?= $this->t('.options') ?></h5>
        <ul>
          <li><?= $this->linkTo(($this->params()->forcegray ? $this->t('.mode_color') : $this->t('.mode_gray')), array_merge($this->params()->get(), [ ['forcegray' => (bool) $this->params()->forcegray] ])) ?>
          <?php if (!$this->params()->threshold) : ?>
          <li><?= $this->linkTo($this->t('.show_more'), array_merge($this->params()->get(), [ ['threshold' => 0 ] ])) ?></li>
          <?php endif ?>
          <?php if ($this->params()->url) : ?>
          <li>
          <?= $this->linkTo($this->t('.upload'), ["post#upload",
              'url'     => ($this->params()->full_url ?: $this->params()->url),
              'tags'    => $this->params()->tags,
              'rating'  => $this->params()->rating,
              'parent'  => $this->params()->parent,
          ])
              ?>
          </li>
          <?php endif ?>
        </ul>
      </div>
    </div>
    <?php if ($this->initial) : ?>
      <div id="duplicate">
        <?= $this->t('.duplicate.info_guide_html', ['guide' => $this->linkTo($this->t('.duplicate.guide'), ['controller' => 'wiki', 'action' => 'show', 'title' => 'duplicate'])]) ?>
        <ul>
        <li>
          <?= $this->t('.duplicate.info_reparent_html', ['reparent' => $this->linkToFunction($this->t('.duplicate.reparent'), "$('mode').value = 'reparent'; PostModeMenu.change();")]) ?>
        </li>
        <li>
          <?= $this->t('.duplicate.info_mark_duplicate_html', ['mark_duplicate' => linkToFunction(t('.duplicate.mark_duplicate'), "$('mode').value = 'dupe'; PostModeMenu.change();")]) ?>
        </li>
        <li>
          <form action="<?= $this->urlFor(["#destroy", 'name' => "destroy"]) ?>" id="destroy" method="post">
            <?= $this->hiddenFieldTag("id", $this->params()->id, ['id' => "destroy_id"]) ?>
            <?= $this->hiddenFieldTag("reason", "duplicate") ?>
            <?= $this->t('.duplicate.info_delete_html', ['delete' => $this->linkToFunction($this->t('.duplicate.delete'), "$('destroy').submit")]) ?>
          </form>
        </li>
        </ul>
        <div id="blacklisted-notice" style="display: none;">
          <?= $this->t(['.duplicate.info_blacklist_html', 'blacklist' => $this->contentTag('b', $this->t('.duplicate.blacklist'))]) ?>
        </div>
      </div>
    <?php endif ?>
    <div class="content">
      <div id="quick-edit" style="display: none; margin-bottom: 1em;">
        <h4><?= $this->t('.edit_tags') ?></h4>
        <?= $this->formTag("#update", function () { ?>
          <?= $this->hiddenFieldTag("id", "") ?>
          <?= $this->hiddenFieldTag("post[old_tags]", "") ?>
          <?= $this->textAreaTag("post[tags]", "", ['size' => "60x2", 'id' => "post_tags"]) ?>
          <?= $this->submitTag($this->t('.update')) ?>
          <?= $this->tag('input', ['type' => 'button', 'value' => $this->t('buttons.cancel'), 'class' => "cancel"]) ?>
        <?php }) ?>
      </div>

      <?php if (!$this->initial) : ?>
      <?= $this->formTag(null, ['multipart' => true, 'id' => "similar-form"], function () { ?>
        <input name="forcegray" type="hidden" value="<?= $this->h($this->params['forcegray']) ?>">
        <input name="services" type="hidden" value="<?= $this->h($this->params['services']) ?>">
        <input name="threshold" type="hidden" value="<?= $this->h($this->params['threshold']) ?>">


        <table class="form">
          <tfoot>
            <tr>
              <td colspan="2"><?= $this->submitTag($this->t('buttons.search'), ['tabindex' => 3, 'accesskey' => "s"]) ?></td>
            </tr>
          </tfoot>
          <tbody>
            <tr>
              <th>
                <label for="url"><?= $this->t('.source') ?></label>
              </th>
              <td>
                <input id="url" name="url" size="50" type="text" tabindex="1" value="<?= $this->h($this->params()->url) ?>">
              </td>
            </tr>
            <tr>
              <th width="20%"><label for="post_file"><?= $this->t('.file') ?></label></th>
              <td width="80%"><input id="file" name="file" size="50" tabindex="2" type="file"></td>
            </tr>
          </tbody>
        </table>
      <?php }) ?>
      <?php endif ?>

      <?php if ($this->posts->any()) : ?>
      <?= $this->partial("posts", ['posts' => $this->posts, 'similar' => $this->similar]) ?>
      <?php if (CONFIG()->similar_image_results_on_new_window) : ?>
      <script>
      (function($){
        $('#post-list-posts a').each(function(){
          $(this).attr('target', '_blank')
        })
      })(jQuery);
      </script>
      <?php endif ?>
      <?php endif ?>

      <div id="paginator"></div>

      <?php if ($this->params()->full_url) : ?>
      <img src="<?= $this->params()->full_url ?>"/>
      <?php endif ?>
    </div>
</div>
<?php $this->contentFor('post_cookie_javascripts', function () { ?>
<script type="text/javascript">
  <?php if (!$this->initial) : ?>
  $("url").focus();
  <?php endif ?>

  <?php if ($this->params()->id) : ?>
  // for post_mode_menu.js:click
  id=<?= $this->params()->id ?>;
  <?php endif ?>

  post_quick_edit = new PostQuickEdit($("quick-edit"));

  PostModeMenu.init()

  var form = $("similar-form");
  // if(form && SimilarWithThumbnailing)
    // new SimilarWithThumbnailing(form);
</script>
<?php }) ?>

<?= $this->partial("footer") ?>

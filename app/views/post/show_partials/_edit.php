<div id="edit" style="display: none;">
  <?= $this->formTag(['post#update', 'id' => $this->post->id], ['id' => 'edit-form', 'level' => 'member'], function () { ?>
    <?= $this->hiddenFieldTag("post[old_tags]", $this->post->cached_tags) ?>
    <table class="form">
      <tfoot>
        <tr>
          <td colspan="2"><?= $this->submitTag($this->t('.save'), ['tabindex' => '11', 'accesskey' => 's']) ?></td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <th width="15%">
            <label class="block" for="post_rating_questionable"><?= $this->t('.rating') ?></label>
            <?php if (!current_user()->is_privileged_or_higher()) : ?>
              <p><?= $this->t(['.rating_info_html', 'more' => $this->linkTo($this->t('.help'), 'help#ratings', ['target' => '_blank'])]) ?></p>
            <?php endif ?>
          </th>
          <td width="85%">
            <?php if ($this->post->is_rating_locked()) : ?>
              <?= $this->t('.rating_locked_info') ?>
            <?php else: ?>
              <?= $this->radioButtonTag("post[rating]", "e", $this->post->rating == "e", ['id' => 'post_rating_explicit', 'tabindex' => '1']) ?>
              <label for="post_rating_explicit"><?= $this->t('ratings.e') ?></label>
              <?= $this->radioButtonTag("post[rating]", "q", $this->post->rating == "q", ['id' => 'post_rating_questionable', 'tabindex' => '2']) ?>
              <label for="post_rating_questionable"><?= $this->t('ratings.q') ?></label>
              <?= $this->radioButtonTag("post[rating]", "s", $this->post->rating == "s", ['id' => 'post_rating_safe', 'tabindex' => '3']) ?>
              <label for="post_rating_safe"><?= $this->t('ratings.s') ?></label>
            <?php endif ?>
          </td>
        </tr>
        <?php if (CONFIG()->enable_parent_posts) : ?>
          <tr>
            <th><label><?= $this->t('.parent') ?></label></th>
            <td><?= $this->textField("post", "parent_id", ['size' => '10', 'tabindex' => '4']) ?></td>
          </tr>
        <?php endif ?>
          <tr>
            <th><label class="block" for="post_is_shown_in_index"><?= $this->t('.shown_in_index') ?></label></th>
            <td><?= $this->checkBox("post", "is_shown_in_index", ['tabindex' => '7']) ?></td>
          </tr>
        <?php if (current_user()->is_privileged_or_higher()) : ?>
          <tr>
            <th><label class="block" for="post_is_note_locked"><?= $this->t('.note_locked') ?></label></th>
            <td><?= $this->checkBox("post", "is_note_locked", ['tabindex' => '7']) ?></td>
          </tr>
          <tr>
            <th><label class="block" for="post_is_rating_locked"><?= $this->t('.rating_locked') ?></label></th>
            <td><?= $this->checkBox("post", "is_rating_locked", ['tabindex' => '8']) ?></td>
          </tr>
        <?php endif ?>
        <tr>
          <th><label class="block" for="post_source"><?= $this->t('.source') ?></label></th>
          <td><?= $this->textField("post", "source", ['size' => '40', 'tabindex' => '9']) ?></td>
        </tr>
          <tr>
            <th>
              <label class="block" for="post_tags"><?= $this->t('.tags') ?></label>
              <?php if (!current_user()->is_privileged_or_higher()) : ?>
                <p><?= $this->t(['.tags_info_html', 'more' => $this->linkTo($this->t('.help'), ['help#tags'], ['target' => '_blank'])]) ?></p>
              <?php endif ?>
            </th>
            <td>
              <?= $this->textArea("post", "tags", ['disabled' => !$this->post->can_be_seen_by(current_user()), 'size' => '50x4', 'tabindex' => '10', 'value' => $this->h($this->post->cached_tags)]) ?>
            <?php if ($this->post->can_be_seen_by(current_user())) : ?>
              <?= $this->linkToFunction($this->t('.related.tags'), "RelatedTags.find('post_tags')") ?> |
              <?= $this->linkToFunction($this->t('.related.artists'), "RelatedTags.find('post_tags', 'artist')") ?> |
              <?= $this->linkToFunction($this->t('.related.characters'), "RelatedTags.find('post_tags', 'char')") ?> |
              <?= $this->linkToFunction($this->t('.related.copyrights'), "RelatedTags.find('post_tags', 'copyright')") ?>
              <?php if (CONFIG()->enable_artists) : ?>
              | <?= $this->linkToFunction($this->t('.find_artists'), "RelatedTags.find_artist(\$F('post_source'))") ?>
              <?php endif ?>
            <?php endif ?>
            </td>
          </tr>
      </tbody>
    </table>
    <div>
      <h5><?= $this->t('.related.tags') ?></h5>
      <div style="margin-bottom: 1em;" id="related"><em><?= $this->t('.related.none') ?></em></div>
    </div>
  <?php }) ?>
</div>

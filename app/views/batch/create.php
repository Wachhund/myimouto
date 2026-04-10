<?php $this->provide('title', $this->t('.title')) ?>
<?php if (empty($this->urls)) : ?>
  <div id="batch-post-source">
    <?= $this->formTag(["#create"], ['level' => 'contributor', 'method' => "get", 'id' => "edit-form"], function () { ?>
      <div id="posts">
        <table class="form">
          <tbody>
            <tr>
              <th> <label for="post_source"><?= $this->t('.url') ?></label> </th>
              <td>
                <input id="post_url" name="url" size="50" tabindex="2" type="text" value="<?= $this->h($this->params()->url) ?>">
              </td>
            </tr>
            <tr>
              <td></td>
              <td> <?= $this->submitTag($this->t('.load_file_index'), ['tabindex' => 8]) ?> </td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php }) ?>
  </div>
<?php else: ?>
  <div id="post-add">
    <div id="static_notice" style="display: none;"></div>

    <?= $this->formTag(['action' => "enqueue"], ['level' => 'contributor', 'multipart' => true, 'id' => "edit-form"], function () { ?>
      <div id="posts">
        <table class="form">
          <tbody>
            <tr>
              <th> <label for="post_files"><?= $this->t('.url') ?></label> </th>
              <td> <?= $this->h($this->source) ?> </td>
            </tr>
            <tr>
              <th> <label for="post_files"><?= $this->t('.files') ?></label> </th>
              <td>
                <select id="files" name="files[]" multiple size="15">
                  <?php foreach ($this->urls as $url) : ?>
                    <option value="<?= $this->h($url) ?>" selected="selected"><?= urldecode(pathinfo($url, PATHINFO_BASENAME)) ?></option>
                  <?php endforeach ?>
                </select>
              </td>
            </tr>

            <tr>
              <th> <label for="post_tags"><?= $this->t('.tags') ?></label> </th>
              <td>
                <?= $this->textArea('post', 'tags', ['value' => $this->params()->tags, 'size' => "60x2", 'tabindex' => 3, 'class' => 'ac-tags']) ?>
              </td>
            </tr>

            <tr>
              <th>
                <label for="post_rating_questionable"><?= $this->t('ratings._') ?></label>
              </th>
              <td>
              <input id="post_rating_explicit" name="post[rating]" type="radio" value="e" <?php if (($this->params()->rating ?: "q") == "e") : ?>checked="checked"<?php endif ?> tabindex="5">
                <label for="post_rating_explicit"><?= $this->t('ratings.e') ?></label>

              <input id="post_rating_questionable" name="post[rating]" type="radio" value="q" <?php if (($this->params()->rating ?: "q") == "q") : ?>checked="checked"<?php endif ?> tabindex="6">
                <label for="post_rating_questionable"><?= $this->t('ratings.q') ?></label>

              <input id="post_rating_safe" name="post[rating]" type="radio" value="s" <?php if (($this->params()->rating ?: "q") == "s") : ?>checked="checked"<?php endif ?> tabindex="7">
                <label for="post_rating_safe"><?= $this->t('ratings.s') ?></label>
              </td>
            </tr>

            <tr>
              <td></td>
              <td>
                <?= $this->submitTag($this->t('.submit'), ['tabindex' => 8, 'accesskey' => "s"]) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php }) ?>
  </div>
<?php endif ?>

<?php $this->contentFor('post_cookie_javascripts', function () { ?>
  <script type="text/javascript">
    if($("post_url"))
      $("post_url").focus();
    else if($("post_tags"))
      $("post_tags").focus();
  </script>
<?php }) ?>


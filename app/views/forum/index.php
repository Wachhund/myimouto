<div id="forum">
  <div id="search" style="margin-bottom: 1em;">
    <?= $this->formTag(['action' => "search"], ['method' => 'get'], function(){ ?>
      <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 40]) ?>
      <?= $this->submitTag($this->t('.search')) ?>
    <?php }) ?>
  </div>

  <table class="nowrap highlightable" width="100%">
    <thead>
      <tr>
        <th><?=$this->t('.post_title') ?></th>
        <th><?=$this->t('.created_by') ?></th>
        <th><?=$this->t('.updated_by') ?></th>
        <th><?=$this->t('.updated') ?></th>
        <th><?=$this->t('.responses') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->forum_posts as $fp) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td class="wrap full">
            <?php if (!current_user()->is_anonymous() && $fp->updated_at > current_user()->last_forum_topic_read_at) : ?>
              <span class="forum-topic unread-topic"><?php if ($fp->is_sticky) : ?><?= $this->t('.sticky') ?>: <?php endif ?><?= $this->linkTo($this->h($fp->title), ['action' => "show", 'id' => $fp->id]) ?></span>
            <?php else: ?>
              <span class="forum-topic"><?php if ($fp->is_sticky) : ?><?=$this->t('.sticky') ?>: <?php endif ?><?= $this->linkTo($this->h($fp->title), ['action' => "show", 'id' => $fp->id]) ?></span>
            <?php endif ?>

            <?php if ($fp->response_count > 30) : ?>
              <?= $this->linkTo($this->t('.last'), ['action' => "show", 'id' => $fp->id, 'page' => ceil($fp->response_count / 30.0)], ['class' => "last-page"]) ?>
            <?php endif ?>

            <?php if ($fp->is_locked) : ?>
              <span class="locked-topic"><?= $this->t('.is_locked') ?></span>
            <?php endif ?>
          </td>
          <td><?= $this->h($fp->author()) ?></td>
          <td><?= $this->h($fp->last_updater()) ?></td>
          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($fp->updated_at)]) ?></td>
          <td><?= $fp->response_count ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div id="paginator">
    <?= $this->willPaginate($this->forum_posts) ?>
  </div>

  <?php $this->contentFor('subnavbar', function(){ ?>
    <li><?= $this->linkTo("New topic", ["#blank"]) ?></li>
    <?php if (!current_user()->is_anonymous()) : ?>
      <li><?= $this->linkToFunction($this->t('.mark'), "Forum.mark_all_read()") ?></li>
    <?php endif ?>
    <li><?= $this->linkTo($this->t('.help'), ['controller' => "help", 'action' => "forum"]) ?></li>
  <?php }) ?>

  <div id="preview" style="display: none; margin: 1em 0;">
  </div>

  <div id="reply" style="display: none;">
    <?= $this->formTag(['action' => "create"], ['level' => 'member'], function() { ?>
      <?= $this->hiddenField("forum_post", "parent_id", ['value' => $this->params()->parent_id]) ?>
      <table>
        <tr>
          <td><label for="forum_post_title"><?=$this->t('.title') ?></label></td>
          <td><?= $this->textField('forum_post', 'title', ['size' => 60]) ?></td>
        </tr>
        <tr>
          <td colspan="2"><?= $this->textArea('forum_post', 'body', ['rows' => 20, 'cols' => 80]) ?></td>
        </tr>
        <tr>
          <td colspan="2">
            <?= $this->submitTag($this->t('.post')) ?>
            <input name="preview" onclick="new Ajax.Updater('preview', '<?= $this->urlFor('#preview') ?>', {asynchronous:true, evalScripts:true, onSuccess:function(request){$('preview').show()}, parameters:Form.serialize(this.form)});" type="button" value="<?= $this->t('.preview') ?>"/>
          </td>
        </tr>
      </table>
    <?php }) ?>
  </div>
</div>

<?php $this->provide('title', $this->h($this->forum_post->title)) ?>
<?php if ($this->forum_post->is_locked) : ?>
  <div class="status-notice">
    <p><?= $this->t('.locked') ?></p>
  </div>
<?php endif ?>

<div id="forum" class="response-list">
  <?php if ($this->page_number <= 1) : ?>
    <?= $this->partial("post", ['post' => $this->forum_post]) ?>
  <?php endif ?>

  <?php foreach ($this->children as $c) : ?>
    <?= $this->partial("post", ['post' => $c]) ?>
  <?php endforeach ?>
</div>

<?php if (!$this->forum_post->is_locked) : ?>
  <div style="clear: both;">

    <div id="preview" class="response-list" style="display: none; margin: 1em 0;">
    </div>

    <div id="reply" style="display: none; clear: both;">
      <?= $this->formTag(['action' => "create"], ['level' => 'member'], function(){ ?>
        <?= $this->hiddenField("forum_post", "title", ['value' => ""]) ?>
        <?= $this->hiddenField("forum_post", "parent_id", ['value' => $this->forum_post->root_id()]) ?>
        <?= $this->textArea('forum_post', 'body', ['rows' => 20, 'cols' => 80, 'value' => ""]) ?>
        <?= $this->submitTag($this->t('.post')) ?>
        <input name="preview" onclick="new Ajax.Updater('preview', '/forum/preview', {asynchronous:true, evalScripts:true, onSuccess:function(request){$('preview').show()}, parameters:Form.serialize(this.form)});" type="button" value="<?= $this->t('.preview') ?>"/>
      <?php }) ?>
    </div>
  </div>
<?php endif ?>

<div id="paginator">
  <?= $this->willPaginate($this->children) ?>
</div>

<script type="text/javascript">
  <?= $this->avatar_init() ?>
  InlineImage.init();
</script>

<?php $this->contentFor('subnavbar', function() { ?>
  <?php if (!$this->forum_post->is_locked) : ?>
    <li><?= $this->linkToFunction($this->t('.reply'), "Element.toggle('reply')") ?></li>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('.list'), ['action' => "index"]) ?></li>
  <li><?= $this->linkTo($this->t('.new'), ['action' => "blank"]) ?></li>
  <?php if (!$this->forum_post->is_parent()) : ?>
    <li><?= $this->linkTo($this->t('.parent'), ['action' => "show", 'id' => $this->forum_post->parent_id]) ?></li>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('.help'), ['controller' => "help", 'action' => "forum"]) ?></li>
<?php }) ?>

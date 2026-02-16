<h4><?= $this->t(['.title', 'name' => $this->pool->pretty_name()]) ?></h4>

<p><?= $this->t('.info') ?></p>

<div style="margin-bottom: 1em;">
  <?= $this->formTag(['#import', 'id' => $this->pool->id, 'format' => 'js'], [
    'method' => 'get',
    'onsubmit' => "new Ajax.Request(this.action, {asynchronous:true, evalScripts:true, method:'get', parameters:Form.serialize(this)}); return false;"
  ], function() { ?>
    <?= $this->textFieldTag('query', $this->params()->query, ['size' => 50]) ?>
    <?= $this->submitTag($this->t('.search')) ?>
  <?php }) ?>
</div>

<div>
  <ul id="post-list-posts">
    <?= $this->formTag(['#import', 'id' => $this->pool->id], function() { ?>
      <?= $this->hiddenFieldTag('id', $this->pool->id) ?>
      <div id="posts"></div>

      <div style="clear: both;">
        <?= $this->submitTag($this->t('.import')) ?>
        <?= $this->buttonToFunction($this->t('buttons.cancel'), 'history.back()') ?>
      </div>
    <?php }) ?>
  </ul>
</div>

<script type="text/javascript">
function removePost(id) {
  if ($("delete-mode") && $("delete-mode").checked) {
    if ($("posts_" + id)) {
      $("posts_" + id).remove();
    }
    if ($("p" + id)) {
      $("p" + id).remove();
    }
    return false;
  }
  return true;
}
</script>

<?= $this->partial("footer") ?>

<div id="comment-edit">
  <h4><?= $this->t('.title') ?></h4>

  <?= $this->formTag("#update", function () { ?>
    <?= $this->hiddenFieldTag("id", $this->params()->id) ?>
    <?= $this->textArea("comment", "body", ['rows' => 10, 'cols' => 60]) ?><br>
    <?= $this->submitTag($this->t('.save')) ?>
  <?php }) ?>
</div>

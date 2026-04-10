<?= $this->formTag("#add_post", function () { ?>
  <?= $this->hiddenFieldTag("post_id", $this->params()->post_id) ?>
  <?= $this->selectTag("pool_id", [$this->options, $this->last_pool_id]) ?>
  <?= $this->buttonToFunction($this->t('.add'), "Pool.add_post({$this->params()->post_id}, \$F('pool_id'))", ['level' => 'member']) ?>
<?php }) ?>

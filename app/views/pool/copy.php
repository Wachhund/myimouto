<?php $this->provide('title', $this->t('.title')) ?>
<h4><?= $this->t('.title') ?></h4>

<?= $this->formTag(function () { ?>
  <?= $this->hiddenFieldTag("id", $this->params()->id) ?>
  <label><?= $this->t('.name') ?></label> <?= $this->textFieldTag("name", $this->new_pool->pretty_name()) ?>
  <?= $this->submitTag($this->t('.copy')) ?> <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()") ?></td>
<?php }) ?>

<?= $this->partial("footer") ?>

<script type="text/javascript">$("name").focus();</script>

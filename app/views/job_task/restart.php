<h4>Restart Job #<?= $this->job_task->id ?></h4>

<?= $this->formTag(function () { ?>
  <?= $this->submitTag($this->t("buttons._yes")) ?>
  <?= $this->buttonToFunction($this->t("buttons._no"), "location.back()") ?>
<?php }) ?>

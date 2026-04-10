<?= $this->formTag("#update", function () { ?>
  <?= $this->hiddenFieldTag("title", $this->params()->title) ?>
  <label for="wiki_page_title">Title</label> <?= $this->textField('wiki_page', 'title') ?><br>
  <?= $this->submitTag("Save") ?> <?= $this->buttonToFunction("Cancel", "history.back()") ?>
<?php }) ?>

<?= $this->partial("footer") ?>

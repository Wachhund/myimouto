<h4><?= $this->t('.title') ?></h4>
<p><?= $this->t(['.confirm', 'name' => $this->artist->name]) ?></p>

<?= $this->formTag([], ['level' => 'privileged'], function(){ ?>
  <button type="submit" name="commit" value="Yes"><?= $this->t('buttons._yes') ?></button>
  <button type="submit"><?= $this->t('buttons._no') ?></button>
<?php }) ?>

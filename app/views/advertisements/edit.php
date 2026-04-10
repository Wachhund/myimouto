<h4><?= $this->t('.title', ['id' => $this->ad->id]) ?></h4>

<?= $this->partial('form', ['ad' => $this->ad]) ?>

<?= $this->linkTo($this->t('buttons.back'), '#index') ?>

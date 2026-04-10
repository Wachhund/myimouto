<h4><?= $this->t('advertisements.new.title') ?></h4>

<?= $this->partial('form', ['ad' => $this->ad]) ?>

<?= $this->linkTo($this->t('buttons.back'), $this->advertisementsPath()) ?>

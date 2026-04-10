<?php if ($this->user) : ?>
  <?php $this->contentFor('subnavbar', function () { ?>
    <li><?= $this->linkTo($this->t('record_add2'), ['action' => "create", 'user_id' => $this->user->id]) ?></li>
    <li><?= $this->linkTo($this->t('record_list'), ['action' => "index", 'user_id' => $this->user->id]) ?></li>
    <li><?= $this->linkTo($this->t('record_list2'), ['action' => "index", 'user_id' => '']) ?></li>
  <?php }) ?>
<?php endif ?>

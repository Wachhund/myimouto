<?= $this->formTag("#update", function() { ?>
  <h4><?= $this->t('sub_edit') ?></h4>
  <div style="margin-bottom: 1em;">
    <?=$this->t('sub_text') ?><?= CONFIG()->max_tag_subscriptions ?><?=$this->t('sub_text2') ?>
  </div>

  <table width="100%" class="highlightable">
    <?= $this->partial("listing", ['tag_subscriptions' => $this->tag_subscriptions, 'csrf_token' => $this->csrf_token]) ?>
  </table>
<?php }) ?>

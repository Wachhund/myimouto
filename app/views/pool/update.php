<h3><?=$this->t('.title') ?></h3>

<?= $this->formTag([], function () { ?>
  <table class="form">
    <tbody>
      <tr>
        <th width="15%"><label for="pool_name"><?=$this->t('.form.name') ?></label></th>
        <td width="85%"><?= $this->textField('pool', 'name', ['value' => $this->pool->pretty_name()]) ?></td>
      </tr>
      <tr>
        <th><label for="pool_description"><?=$this->t('.form.description') ?></label></th>
        <td><?= $this->textArea('pool', 'description', ['size' => "40x10"]) ?></td>
      </tr>
      <tr>
        <th>
          <label for="pool_is_public"><?=$this->t('.form.is_public._') ?></label>
          <p><?=$this->t('.form.is_public.info') ?></p>
        </th>
        <td><?= $this->checkBox("pool", "is_public") ?></td>
      </tr>
      <tr>
        <th>
          <label for="pool_is_active"><?=$this->t('.form.is_active._') ?></label>
          <p><?=$this->t('.form.is_active.info') ?></p>
        </th>
        <td><?= $this->checkBox("pool", "is_active") ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('buttons.save')) ?> <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()") ?></td>
      </tr>
    </tbody>
  </table>
<?php }) ?>

<?= $this->partial("footer") ?>

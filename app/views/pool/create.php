<h3><?=$this->t('.title') ?></h3>

<?= $this->formTag(['action' => "create"], ['level' => 'member'], function () { ?>
  <table class="form">
    <tbody>
      <tr>
        <th><label for="pool_name"><?=$this->t('.name') ?></label></th>
        <td><?= $this->textField('pool', 'name') ?></td>
      </tr>
      <tr>
        <th><label for="pool_is_public"><?=$this->t('.is_public') ?></label></th>
        <td><?= $this->checkBox("pool", "is_public") ?></td>
      </tr>
      <tr>
        <th><label for="pool_description"><?=$this->t('.description') ?></label></th>
        <td><?= $this->textArea('pool', 'description', ['size' => "40x10"]) ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('.save')) ?> <?= $this->buttonToFunction($this->t('.cancel'), "history.back()") ?></td>
      </tr>
    </tbody>
  </table>
<?php }) ?>

<?= $this->partial("footer") ?>

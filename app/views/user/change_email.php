<?php # iTODO: formFor support?>
<?php $this->provide('title', $this->t('.title')) ?>
<h1><?= $this->t('.title') ?></h1>
<div id="user-edit">
  <?= $this->formTag([ 'action' => 'update' ], function () { ?>
    <?= $this->hiddenField('render', 'view', ['value' => 'change_email']) ?>
    <?php # Just so the current email carries over on error -?>
    <?= $this->hiddenField('user', 'current_email') ?>
    <?= $this->partial('shared/error_messages', ['object' => $this->user]) ?>
    <table>
      <tbody>
        <tr>
          <th><?= $this->t('.current_email') ?></th>
          <td><?= current_user()->current_email ?></td>
        </tr>
        <tr>
          <th><label for="user_email"><?= $this->t('.new_email') ?></label></th>
          <td><?= $this->textField('user', 'email') ?></td>
        </tr>
        <tr>
          <th><label for="user_current_password"><?= $this->t('.current_password') ?></label></th>
          <td><?= $this->passwordField('user', 'current_password') ?></td>
        </tr>
        <tr>
          <td><?= $this->submitTag($this->t('buttons.save')) ?> <?= $this->submitTag($this->t('buttons.cancel')) ?></td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>

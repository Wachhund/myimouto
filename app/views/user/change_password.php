<?php $this->provide('title', $this->t('.title')) ?>
<h1><?= $this->t('.title') ?></h1>
<div id="user-edit">
  <?= $this->formTag([ 'action' => 'update' ], function () { ?>
    <?= $this->hiddenField('render', 'view', ['value' => 'change_password']) ?>
    <?= $this->partial('shared/error_messages', ['object' => current_user()]) ?>
    <table>
      <tbody>
        <tr>
          <th><label for="user_current_password">Current password</label></th>
          <td><?= $this->passwordField('user', 'current_password') ?></td>
        </tr>
        <tr>
          <th><label for="user_password"><?= $this->t('.new_password') ?></label></th>
          <td><?= $this->passwordField('user', 'password') ?></td>
        </tr>
        <tr>
          <th><label for="user_password">Password confirmation</label></th>
          <td><?= $this->passwordField('user', 'password_confirmation') ?></td>
        </tr>
        <tr>
          <td><?= $this->submitTag($this->t('buttons.save')) ?> <?= $this->submitTag($this->t('buttons.cancel')) ?></td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>

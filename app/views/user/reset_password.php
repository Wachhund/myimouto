<div class="page">
<?php if (isset($this->reset_token)): ?>
  <h4>Set New Password</h4>
  <p>Enter your new password below.</p>

  <?= $this->formTag(['action' => 'reset_password', 'token' => $this->reset_token], function(){ ?>
    <?= $this->hiddenFieldTag('token', $this->reset_token) ?>
    <table class="form">
      <tbody>
        <tr>
          <th><label class="block" for="user_password">New Password</label></th>
          <td><?= $this->passwordFieldTag("user[password]", "", ['id' => 'user_password', 'size' => 30]) ?></td>
        </tr>
        <tr>
          <th><label class="block" for="user_password_confirm">Confirm Password</label></th>
          <td><?= $this->passwordFieldTag("user[password_confirm]", "", ['id' => 'user_password_confirm', 'size' => 30]) ?></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">
            <?= $this->submitTag("Reset Password") ?>
          </td>
        </tr>
      </tfoot>
    </table>
  <?php }) ?>
<?php else: ?>
  <h4><?= $this->t('user_reset') ?></h4>
  <p><?= $this->t('user_reset_text') ?></p>

  <?= $this->formTag(['action' => 'reset_password'], function(){ ?>
    <table class="form">
      <tbody>
        <tr>
          <th>
            <label class="block" for="user_name"><?= $this->t('user_reset_name') ?></label>
          </th>
          <td>
            <?=$this->textField("user", "name") ?>
          </td>
        </tr>
        <tr>
          <th><label class="block" for="user_email"><?= $this->t('user_reset_email') ?></label></th>
          <td><?=$this->textField("user", "email") ?></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">
            <?= $this->submitTag($this->t('user_resend_submit')) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  <?php }) ?>
<?php endif; ?>
</div>

<?= $this->partial("footer") ?>

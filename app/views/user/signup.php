<div style="width: 40em;">
<h4><?= $this->t('user_signup_text') ?></h4>

<?php if (!CONFIG()->enable_signups) : ?>
  <p><?= $this->t('user_signup_text2') ?></p>
<?php else: ?>
  <p><?= $this->t('user_signup_text3') ?><a href="/static/terms_of_service"><?= $this->t('user_signup_text4') ?></a><?= $this->t('user_signup_text5') ?><strong><?= $this->t('user_signup_text6') ?></strong></p>

  <?= $this->formTag('user#create', function(){ ?>
    <table class="form">
      <tfoot>
        <tr>
          <td colspan="2">
            <input type="submit" value="<?= $this->t('user_signup') ?>">
          </td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <th width="15%">
            <label class="block" for="user_name"><?= $this->t('user_signup_name') ?></label>
            <p><?= $this->t('user_signup_text') ?></p>
          </th>
          <td width="85%">
            <?= $this->textField("user", "name", ['size' => '30', 'autocomplete' => 'username', 'spellcheck' => 'false']) ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_email"><?= $this->t('user_signup_email') ?></label>
            <p><?= $this->t('user_signup_email_text') ?></p>
          </th>
          <td>
            <?= $this->textField("user", "email", ['size' => '30', 'type' => 'email', 'autocomplete' => 'email']) ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_password"><?= $this->t('user_password') ?></label>
            <p><?= $this->t('user_password_text') ?></p>
          </th>
          <td>
            <?= $this->passwordField("user", "password", ['size' => '30', 'autocomplete' => 'new-password']) ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_password_confirmation"><?= $this->t('user_password_confirm') ?></label>
          </th>
          <td>
            <?= $this->passwordField("user", "password_confirmation", ['size' => '30', 'autocomplete' => 'new-password']) ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
<?php endif ?>
</div>
<?= $this->partial("footer") ?>

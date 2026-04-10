<div id="user-login" class="page">
  <h4><?= $this->t('user_login_text1') ?></h4>
  <?php if (current_user()->is_unactivated()) : ?>
    <p><?= $this->t('user_login_text2') ?><?= $this->linkTo($this->t('user_login_text3'), ['action' => 'resend_confirmation']) ?><?= $this->t('user_login_text4') ?><?= $this->h(current_user()->email) ?>.</p>
  <?php else: ?>
    <p>
      <?= $this->t('user_login_text5') ?><?= $this->h(CONFIG()->app_name) ?>.
      <?php if (!current_user()->is_anonymous()) : ?>
        <?= $this->t('user_login_text6') ?><?= $this->linkTo($this->t('user_login_text7'), ['action' => 'reset_password']) ?><?= $this->t('user_login_text8') ?>
      <?php endif ?>
      <?php if (current_user()->is_anonymous()) : ?>
        <?php if (CONFIG()->enable_signups) : ?>
          <?= $this->t('user_login_text9') ?><?= $this->linkTo($this->t('user_login_text10'), ['action' => 'signup']) ?>.
        <?php else: ?>
          <?= $this->t('user_login_text11') ?>
        <?php endif ?>
      <?php endif ?>
    </p>
  <?php endif ?>

  <?= $this->formTag(['action' => 'authenticate'], function () { ?>
    <?= $this->hiddenFieldTag("url", $this->params()->url) ?>
    <table class="form">
      <tr>
        <th width="15%"><label class="block" for="user_name"><?= $this->t('layout_name') ?></label></th>
        <td width="85%"><?= $this->textField("user", "name", ['tabindex' => '1']) ?></td>
      </tr>
      <tr>
        <th><label class="block" for="user_password"><?= $this->t('layout_password') ?></label></th>
        <td><?= $this->passwordField("user", "password", ['tabindex' => '1']) ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('layout_login'), ['tabindex' => '1']) ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>

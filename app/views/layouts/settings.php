<?php $this->contentFor('content', function() { ?>
  <div id="header">
    <div id="title">
      <h2 id="site-title">
        <?= $this->linkTo($this->imageTag('/images/logo_small.png', ['alt' => CONFIG()->app_name, 'size' => '484x75', 'id' => 'logo']), $this->rootPath()) ?>
      </h2>
    </div>
    <?= $this->partial('layouts/menu') ?>
  </div>
  <?php $this->partial('layouts/notice') ?>

  <div class="blocked" id="block-reason" style="display: none;">
  </div>

  <div id="main">
    <div id="menu" class="settings">
      <h5><?= $this->t('layouts.settings.menu.title') ?></h5>
      <ul>
        <li><?= $this->linkTo($this->t('user.edit.title'), ['user#edit']) ?></li>
        <li><?= $this->linkTo($this->t('settings.api.show.title'), ['settings_api#show']) ?></li>
        <li><?= $this->linkTo('API Keys', ['api_key#index']) ?></li>
        <li><?= $this->linkTo($this->t('user.change_email.title'), $this->userChangeEmailPath()) ?></li>
        <li><?= $this->linkTo($this->t('user.change_password.title'), $this->userChangePasswordPath()) ?></li>
      </ul>
    </div>
    <div id="content">
      <?= $this->content() ?>
    </div>
  </div>
<?php }) ?>
<?= $this->render(['template' => 'layouts/application']) ?>

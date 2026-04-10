<?php $this->contentFor('content', function () { ?>
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
    <aside id="menu" class="settings">
      <h5><?= $this->t('layouts.admin.menu.title') ?></h5>
      <ul>
        <li><?= $this->linkTo($this->t('layouts.admin.menu.edit_user'), ['admin#edit_user']) ?></li>
        <li><?= $this->linkTo($this->t('layouts.admin.menu.reset_password'), ['admin#reset_password']) ?></li>
        <li><?= $this->linkTo($this->t('layouts.admin.menu.fix_tag_count'), ['admin#recalculate_tag_count'], ['method' => 'post']) ?></li>
        <li><?= $this->linkTo($this->t('layouts.admin.menu.purge_tags'), ['admin#purge_tags'], ['confirm' => 'Confirm purge tags?', 'method' => 'post']) ?></li>
      </ul>
    </aside>
    <main id="content">
      <?= $this->content() ?>
    </main>
  </div>
<?php }) ?>
<?= $this->render(['template' => 'layouts/application']) ?>

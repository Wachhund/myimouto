<?php $this->provide('title', $this->t('.title')) ?>
<h1><?= $this->t('.title') ?></h1>
<div id="user-edit">
  <?= $this->formTag('user#update', function () { ?>
    <table class="form">
      <tfoot>
        <tr>
          <td colspan="2">
            <?= $this->submitTag($this->t('user_save')) ?> <?= $this->submitTag($this->t('user_cancel')) ?>
          </td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <th width="15%">
            <label class="block" for="user_blacklisted_tags"><?= $this->t('user1') ?></label>
            <p><?= $this->t('user2') ?></p>
          </th>
          <td width="85%">
            <?= $this->textAreaTag("user[blacklisted_tags]", $this->user->blacklisted_tags(), ['size' => '80x6']) ?>
          </td>
        </tr>
        <tr>
          <th>
            <?= $this->t('.email') ?>
            <?php if (CONFIG()->enable_account_email_activation) : ?>
              <p><?= $this->t('user4') ?></p>
            <?php else: ?>
              <p><?= $this->t('user5') ?></p>
            <?php endif ?>
          </th>
          <td>
            <?= !$this->user->email ? $this->t('.no_email') : $this->user->email ?> (<?= $this->linkTo($this->t('.update_email'), ['action' => 'change_email']) ?>)
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_tag_subscriptions_text"><?= $this->t('user6') ?></label>
          </th>
          <td class="large-text">
            <?= $this->partial("tag_subscription/user_listing", ['user' => $this->user]) ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_my_tags"><?= $this->t('user7') ?></label>
            <p><?= $this->t('user8') ?><a href="/post/upload"><?= $this->t('user9') ?></a><?= $this->t('user10') ?></p>
          </th>
          <td>
            <?= $this->textArea("user", "my_tags", ['size' => '40x5']) ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_always_resize_images"><?= $this->t('user11') ?></label>
            <p><?= $this->t('user12') ?></p>
          </th>
          <td>
            <?= $this->checkBox("user", "always_resize_images") ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_receive_dmails"><?= $this->t('user13') ?></label>
            <p><?= $this->t('user14') ?></p>
          </th>
          <td>
            <?= $this->checkBox("user", "receive_dmails") ?>
          </td>
        </tr>
        <?php if (CONFIG()->image_samples && !CONFIG()->force_image_samples) : ?>
        <tr>
          <th>
            <label class="block" for="user_show_samples"><?= $this->t('user15') ?></label>
            <p><?= $this->t('user16') ?></p>
          </th>
          <td>
            <?= $this->checkBox("user", "show_samples") ?>
          </td>
        </tr>
        <?php endif ?>
        <tr>
          <th>
            <label class="block" for="user_use_browser"><?= $this->t('user17') ?></label>
            <p><?= $this->t('user18') ?></p>
          </th>
          <td>
            <?= $this->checkBox("user", "use_browser") ?>
          </td>
        </tr>
        <tr>
          <th>
            <label class="block" for="user_show_advanced_editing"><?= $this->t('user19') ?></label>
            <p><?= $this->t('user20') ?></p>
          </th>
          <td>
            <?= $this->checkBox("user", "show_advanced_editing") ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>

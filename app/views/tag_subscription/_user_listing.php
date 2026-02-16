<?php $has_subscriptions = !empty($user->tag_subscriptions) && $user->tag_subscriptions->size() > 0; ?>

<?php if (!$has_subscriptions) : ?>
  <?= $this->t('sub_none') ?>
<?php else : ?>
  <?= $this->tag_subscription_listing($user) ?>
<?php endif ?>

<?php if (current_user()->id == $user->id) : ?>
  (<?= $this->linkTo($this->t('sub_edit'), 'tag_subscription#index') ?>)
<?php endif ?>

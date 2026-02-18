<?php
$listingUser = $this->user ?? ($user ?? null);
$subscriptions = $listingUser ? $listingUser->tag_subscriptions : null;
$has_subscriptions = $subscriptions && $subscriptions->size() > 0;
?>

<?php if (!$has_subscriptions) : ?>
  <?= $this->t('sub_none') ?>
<?php else : ?>
  <?= $this->tag_subscription_listing($listingUser) ?>
<?php endif ?>

<?php if ($listingUser && current_user()->id == $listingUser->id) : ?>
  (<?= $this->linkTo($this->t('sub_edit'), 'tag_subscription#index') ?>)
<?php endif ?>

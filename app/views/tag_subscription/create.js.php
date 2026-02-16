<?php if ($this->tag_subscription) : ?>
  $('tag-subscription-body').insert({ bottom: '<?= $this->escapeJavascript($this->partial('listing_row', ['tag_subscription' => $this->tag_subscription, 'csrf_token' => $this->csrf_token])) ?>'});
<?php else: ?>
  notice('<?= "You can only create up to " . CONFIG()->max_tag_subscriptions . " tag subscriptions" ?>');
<?php endif ?>

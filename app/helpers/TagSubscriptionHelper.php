<?php

class TagSubscriptionHelper extends Rails\ActionView\Helper
{
    public function tag_subscription_listing($user)
    {
        if (empty($user) || empty($user->tag_subscriptions)) {
            return '';
        }

        $html = [];
        foreach ($user->tag_subscriptions as $tag_subscription) {
            $name = (string) $tag_subscription->name;
            $query = trim((string) $tag_subscription->tag_query);
            $group_links = [];

            foreach (array_filter(preg_split('/\s+/', $query)) as $tag) {
                $group_links[] = $this->linkTo($this->h($tag), ['post#index', 'tags' => $tag]);
            }

            $subscription_link = $this->linkTo(
                $this->h($name),
                ['post#index', 'tags' => 'sub:' . $user->name . ':' . $name],
            );

            $html[] = '<span class="group"><strong>' . $subscription_link . '</strong>: ' . implode(' ', $group_links) . '</span>';
        }

        return implode(' ', $html);
    }
}

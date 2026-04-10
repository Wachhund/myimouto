<?php # Facebook Open Graph
# Reference: https://developers.facebook.com/docs/opengraphprotocol/?>
<?= $this->tag('meta', ['property' => 'og:title', 'content' => $this->html_title()]) ?>
<?= $this->tag('meta', ['property' => 'og:type', 'content' => 'article']) ?>
<?= $this->tag('meta', ['property' => 'og:url', 'content' => $this->urlFor(['post#show', 'id' => $this->post->id, 'only_path' => false])]) ?>
<?= $this->tag('meta', ['property' => 'og:image', 'content' => $this->post->sample_url()]) ?>
<?= $this->tag('meta', ['property' => 'og:site_name', 'content' => CONFIG()->app_name]) ?>
<?= $this->tag('meta', ['property' => 'og:description', 'content' => $this->post->tags()]) ?>
<?php # Reddit Thumbnail?>
<?= $this->tag('link', ['rel' => 'image_src', 'href' => $this->post->sample_url()]) ?>

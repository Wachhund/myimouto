<?php foreach ($this->posts as $post) : ?>
  <?= $this->linkTo($this->imageTag($post->preview_url(), ['width' => $post->preview_dimensions()[0], 'height' => $post->preview_dimensions()[1], 'style' => "margin: 2em;", 'title' => $post->tags(), 'loading' => 'lazy']), ["post#show", 'id' => $post->id]) ?>
<?php endforeach ?>

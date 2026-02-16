<h3><?= $this->t('.title') ?></h3>
<ul>
<?php foreach ($this->users as $u) : ?>
  <li><?= $this->linkTo($this->h($u->pretty_name()), ['post#index', 'tags' => 'vote:3:' . $u->name . ' order:vote']) ?></li>
<?php endforeach ?>
</ul>

<?= $this->partial('footer') ?>

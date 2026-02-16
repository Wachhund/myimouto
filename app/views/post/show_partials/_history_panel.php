<div>
  <h5><?= $this->t('.title') ?></h5>
  <ul>
    <li><?= $this->linkTo($this->t('.tags'), ['history#index', 'search' => 'post:' . $this->post->id]) ?></li>
    <li><?= $this->linkTo($this->t('.notes'), ['note#history', 'post_id' => $this->post->id]) ?></li>
  </ul>
</div>

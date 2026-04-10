<div id="post-view">
  <div class="sidebar">
    <div>
      <h5><?= $this->t('.related._') ?></h5>
      <ul>
        <li><?= $this->linkTo($this->t('.related.previous'), ['post#show', 'id' => $this->params()->id - 1]) ?></li>
        <li><?= $this->linkTo($this->t('.related.next'), ['post#show', 'id' => $this->params()->id + 1]) ?></li>
        <li><?= $this->linkTo($this->t('.related.random'), 'post#random') ?></li>
      </ul>
    </div>
  </div>
  <div>
    <p><?= $this->t('.info') ?></p>
  </div>
</div>

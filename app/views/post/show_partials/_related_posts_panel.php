<div>
  <h5><?= $this->t('.title') ?></h5>
  <ul>
    <li><?= $this->linkToIf($this->post->previous_id(), $this->t('.previous'), ['post#show', 'id' => $this->post->previous_id()]) ?></li>
    <li><?= $this->linkToIf($this->post->next_id(), $this->t('.next'), ['post#show', 'id' => $this->post->next_id()]) ?></li>
    <?php if ($this->post->parent_id) : ?>
      <li><?= $this->linkTo($this->t('.parent'), ['post#show', 'id' => $this->post->parent_id]) ?></li>
    <?php endif ?>
    <li><?= $this->linkTo($this->t('.random'), 'post#random') ?></li>
    <?php if (current_user()->is_member_or_higher()) : ?>
    <?php if (!$this->post->is_deleted() || $this->post->image()) : ?>
      <li><a id="find-dupes"><?= $this->t('.find.duplicate') ?></a><?php #= linkTo "Find dupes", 'post#similar', 'id' => $this->post->id, 'services' => 'local'?></li>
      <li><a id="find-similar"><?= $this->t('.find.similar') ?></a><?php #= linkTo "Find similar", 'post#similar', 'id' => $this->post->id, 'services' => 'all'?></li>
      <script type="text/javascript">
        $("find-dupes").href = '<?= $this->urlFor(['post#similar', 'id' => $this->post->id, 'services' => 'local']) ?>';
        $("find-similar").href = '<?= $this->urlFor(['post#similar', 'id' => $this->post->id, 'services' => 'all']) ?>';
      </script>
    <?php endif ?>
    <?php endif ?>
  </ul>
</div>
